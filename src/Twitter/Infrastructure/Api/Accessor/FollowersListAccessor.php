<?php
declare (strict_types=1);

namespace App\Twitter\Infrastructure\Api\Accessor;

use App\Twitter\Domain\Api\Accessor\ListAccessorInterface;
use App\Twitter\Infrastructure\Api\Resource\FollowersList;
use App\Twitter\Domain\Api\Resource\ResourceList;
use App\Twitter\Domain\Api\Selector\ListSelectorInterface;
use App\Twitter\Domain\Api\Accessor\ApiAccessorInterface;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

class FollowersListAccessor implements ListAccessorInterface
{
    private ApiAccessorInterface $accessor;
    private LoggerInterface $logger;

    public function __construct(
        ApiAccessorInterface $accessor,
        LoggerInterface $logger
    ) {
        $this->accessor = $accessor;
        $this->logger = $logger;
    }

    public function getListAtCursor(
        ListSelectorInterface $selector,
        Closure $onFinishCollection = null
    ): ResourceList {
        try {
            $followersListEndpoint = $this->getFollowersListEndpoint();

            $endpoint = strtr(
                $followersListEndpoint,
                [
                    '{{ screen_name }}' => $selector->screenName(),
                    '{{ cursor }}' => $selector->cursor(),
                ]
            );

            $followersList = (array) $this->accessor->contactEndpoint($endpoint);

            if (is_callable($onFinishCollection)) {
                $onFinishCollection($followersList);
            }

            return FollowersList::fromResponse($followersList);
        } catch (Throwable $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['screen_name' => $selector->screenName()]
            );

            throw $exception;
        }
    }

    private function getFollowersListEndpoint(): string {
        return implode([
            $this->accessor->getApiBaseUrl(),
            '/followers/list.json?',
            'count=200',
            '&skip_status=false',
            '&cursor={{ cursor }}',
            '&screen_name={{ screen_name }}'
        ]);
    }
}