<?php

namespace App\Aggregate\Controller;

use App\Cache\RedisCache;
use App\Http\PaginationParams;
use App\Member\MemberInterface;
use App\Member\Repository\MemberSubscriptionRepository;
use App\Security\Cors\CorsHeadersAwareTrait;
use App\Security\Exception\UnauthorizedRequestException;
use App\Security\HttpAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WeavingTheWeb\Bundle\ApiBundle\Entity\AggregateIdentity;

class SubscriptionController
{
    use CorsHeadersAwareTrait;

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
     * @var MemberSubscriptionRepository
     */
    public $memberSubscriptionRepository;

    /**
     * @var HttpAuthenticator
     */
    public $httpAuthenticator;

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getMemberSubscriptions(Request $request)
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
            $member = $this->httpAuthenticator->authenticateMember($request);
        } catch (UnauthorizedRequestException $exception) {
            return $unauthorizedJsonResponse;
        }

        $paginationParams = PaginationParams::fromRequest($request);

        $aggregateIdentity = null;
        if ($request->get('aggregateId')) {
            $aggregateIdentity = new AggregateIdentity(intval($request->get('aggregateId')));
        }

        $client = $this->redisCache->getClient();
        $cacheKey = $this->getCacheKey($member, $paginationParams, $aggregateIdentity);
        $memberSubscriptions = $client->get($cacheKey);

        if (!$memberSubscriptions) {
            $memberSubscriptions = $this->memberSubscriptionRepository->getMemberSubscriptions(
                $member,
                $paginationParams,
                $aggregateIdentity
            );
            $memberSubscriptions = json_encode($memberSubscriptions);
            $client->setex($cacheKey, 3600, $memberSubscriptions);
        }

        $memberSubscriptions = json_decode($memberSubscriptions, $asArray = true);

        return new JsonResponse(
            $memberSubscriptions['subscriptions'],
            200,
            array_merge(
                $corsHeaders,
                [
                    'x-total-pages' => $memberSubscriptions['total_subscriptions'],
                    'x-page-index' => $paginationParams->pageIndex
                ]
            )
        );
    }

    /**
     * @param MemberInterface   $member
     * @param PaginationParams  $paginationParams
     * @param AggregateIdentity $aggregateIdentity
     * @return string
     */
    private function getCacheKey(
        MemberInterface $member,
        PaginationParams $paginationParams,
        AggregateIdentity $aggregateIdentity = null
    ): string {
        return sprintf(
            '%s:%s:%s/%s',
            $aggregateIdentity ?: '',
            $member->getId(),
            $paginationParams->pageSize,
            $paginationParams->pageIndex
        );
    }
}
