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

        $client = $this->redisCache->getClient();
        $cacheKey = $this->getCacheKey($member, $paginationParams);
        $memberSubscriptions = $client->get($cacheKey);

        if (!$memberSubscriptions) {
            $memberSubscriptions = $this->memberSubscriptionRepository->getMemberSubscriptions(
                $member,
                $paginationParams
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
     * @param MemberInterface $member
     * @param PaginationParams                 $paginationParams
     * @return string
     */
    private function getCacheKey(MemberInterface $member, PaginationParams $paginationParams): string
    {
        return $member->getId() . ':' . $paginationParams->pageSize . '/' . $paginationParams->pageIndex;
    }
}
