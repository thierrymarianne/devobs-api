<?php

namespace App\Aggregate\Controller;

use App\Cache\RedisCache;
use App\Http\PaginationParams;
use App\Member\Exception\InvalidMemberException;
use App\Member\MemberInterface;
use App\Member\Repository\MemberSubscriptionRepository;
use App\Security\AuthenticationTokenValidationTrait;
use App\Security\Cors\CorsHeadersAwareTrait;
use App\Security\Exception\UnauthorizedRequestException;
use App\Security\HttpAuthenticator;
use App\StatusCollection\Messaging\Exception\InvalidMemberAggregate;
use App\StatusCollection\Messaging\MessagePublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WeavingTheWeb\Bundle\ApiBundle\Entity\AggregateIdentity;
use WeavingTheWeb\Bundle\ApiBundle\Entity\Token;
use WTW\UserBundle\Repository\UserRepository;

class SubscriptionController
{
    use CorsHeadersAwareTrait;
    use AuthenticationTokenValidationTrait;

    /**
     * @var string
     */
    public $allowedOrigin;

    /**
     * @var string
     */
    public $environment;

    /**
     * @var RedisCache
     */
    public $redisCache;

    /**
     * @var UserRepository
     */
    public $memberRepository;

    /**
     * @var MemberSubscriptionRepository
     */
    public $memberSubscriptionRepository;

    /**
     * @var MessagePublisher
     */
    public $messagePublisher;

    /**
     * @var HttpAuthenticator
     */
    public $httpAuthenticator;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getMemberSubscriptions(Request $request): JsonResponse
    {
        $memberOrJsonResponse = $this->authenticateMember($request);
        if ($memberOrJsonResponse instanceof JsonResponse) {
            return $memberOrJsonResponse;
        }

        $memberSubscriptions = $this->getCachedMemberSubscriptions(
            $request,
            $memberOrJsonResponse
        );
        if (!$memberSubscriptions) {
            $memberSubscriptions = $this->memberSubscriptionRepository->getMemberSubscriptions(
                $memberOrJsonResponse,
                $request
            );
            $memberSubscriptions = json_encode($memberSubscriptions);

            $client = $this->redisCache->getClient();
            $cacheKey = $this->getCacheKey($memberOrJsonResponse, $request);
            $client->setex($cacheKey, 3600, $memberSubscriptions);
        }

        $memberSubscriptions = json_decode($memberSubscriptions, $asArray = true);
        $paginationParams = PaginationParams::fromRequest($request);

        return new JsonResponse(
            $memberSubscriptions['subscriptions'],
            200,
            array_merge(
                $this->getAccessControlOriginHeaders($this->environment, $this->allowedOrigin),
                [
                    'x-total-pages' => $memberSubscriptions['total_subscriptions'],
                    'x-page-index' => $paginationParams->pageIndex
                ]
            )
        );
    }

    /**
     * @param MemberInterface   $member
     * @param Request           $request
     * @return string
     */
    private function getCacheKey(
        MemberInterface $member,
        Request $request
    ): string {
        $paginationParams = PaginationParams::fromRequest($request);
        $aggregateIdentity = AggregateIdentity::fromRequest($request);

        return sprintf(
            '%s:%s:%s/%s',
            $aggregateIdentity ?: '',
            $member->getId(),
            $paginationParams->pageSize,
            $paginationParams->pageIndex
        );
    }

    /**
     * @param Request $request
     * @return MemberInterface|JsonResponse|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function requestMemberSubscriptionStatusCollection(Request $request): JsonResponse
    {
        $memberOrJsonResponse = $this->authenticateMember($request);

        if ($memberOrJsonResponse instanceof JsonResponse) {
            return $memberOrJsonResponse;
        }

        $memberSubscriptions = $this->memberSubscriptionRepository->getMemberSubscriptions($memberOrJsonResponse);

        /** @var Token $token */
        $token = $this->guardAgainstInvalidAuthenticationToken($this->getCorsOptionsResponse(
            $this->environment,
            $this->allowedOrigin
        ));

        array_walk(
            $memberSubscriptions['subscriptions']['subscriptions'],
            function (array $subscription) use ($token) {
                try {
                    $member = InvalidMemberException::ensureMemberHavingUsernameIsAvailable(
                        $this->memberRepository,
                        $subscription['username']
                    );
                    $this->messagePublisher->publishMemberAggregateMessage($member, $token);
                } catch (InvalidMemberException|InvalidMemberAggregate $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        );

        return new JsonResponse('', 204, []);
    }

    /**
     * @param Request $request
     * @return MemberInterface|JsonResponse|null
     */
    private function authenticateMember(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse(
                $this->environment,
                $this->allowedOrigin
            );
        }

        $corsHeaders = $this->getAccessControlOriginHeaders($this->environment, $this->allowedOrigin);
        $unauthorizedJsonResponse = new JsonResponse(
            'Unauthorized request',
            403,
            $corsHeaders
        );

        try {
            return $this->httpAuthenticator->authenticateMember($request);
        } catch (UnauthorizedRequestException $exception) {
            return $unauthorizedJsonResponse;
        }
    }

    /**
     * @param Request $request
     */
    private function willCacheResponse(Request $request): bool
    {
        $willCacheResponse = true;
        if ($request->headers->has('x-no-cache') &&
            $request->headers->get('x-no-cache')) {
            $willCacheResponse = ! boolval($request->headers->get('x-no-cache'));
        }

        return $willCacheResponse;
    }

    /**
     * @param Request                $request
     * @param                        $memberOrJsonResponse
     * @return string
     */
    private function getCachedMemberSubscriptions(
        Request $request,
        $memberOrJsonResponse
    ): string {
        $memberSubscriptions = '';
        if ($this->willCacheResponse($request)) {
            $client = $this->redisCache->getClient();
            $cacheKey = $this->getCacheKey($memberOrJsonResponse, $request);
            $memberSubscriptions = $client->get($cacheKey);
            if (is_null($memberSubscriptions)) {
                $memberSubscriptions = '';
            }
        }

        return $memberSubscriptions;
    }
}
