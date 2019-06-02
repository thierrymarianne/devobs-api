<?php

namespace App\Aggregate\Controller;

use App\Cache\RedisCache;
use App\Http\PaginationParams;
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

        $memberSubscriptions = $this->memberSubscriptionRepository->getMemberSubscriptions(
            $member,
            $paginationParams
        );

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
}
