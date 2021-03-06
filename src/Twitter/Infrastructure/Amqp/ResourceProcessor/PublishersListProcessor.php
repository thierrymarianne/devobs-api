<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Amqp\ResourceProcessor;

use App\Twitter\Domain\Api\Model\TokenInterface;
use App\Twitter\Domain\Api\Resource\MemberCollectionInterface;
use App\Twitter\Domain\Curation\PublicationStrategyInterface;
use App\Twitter\Domain\Membership\Exception\MembershipException;
use App\Twitter\Infrastructure\Api\Resource\MemberCollection;
use App\Twitter\Domain\Resource\MemberIdentity;
use App\Twitter\Domain\Resource\PublishersList;
use App\Twitter\Infrastructure\Amqp\Exception\ContinuePublicationException;
use App\Twitter\Infrastructure\Amqp\Exception\StopPublicationException;
use App\Twitter\Infrastructure\Api\Exception\CanNotReplaceAccessTokenException;
use App\Twitter\Infrastructure\DependencyInjection\Collection\MemberProfileCollectedEventRepositoryTrait;
use App\Twitter\Infrastructure\DependencyInjection\Collection\PublishersListCollectedEventRepositoryTrait;
use App\Twitter\Infrastructure\DependencyInjection\Membership\MemberIdentityProcessorTrait;
use App\Twitter\Infrastructure\DependencyInjection\TokenChangeTrait;
use App\Twitter\Infrastructure\DependencyInjection\TranslatorTrait;
use App\Twitter\Domain\Api\Accessor\ApiAccessorInterface;
use App\Twitter\Infrastructure\Exception\EmptyListException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_unshift;
use function sprintf;

class PublishersListProcessor implements PublishersListProcessorInterface
{
    use MemberIdentityProcessorTrait;
    use MemberProfileCollectedEventRepositoryTrait;
    use PublishersListCollectedEventRepositoryTrait;
    use TokenChangeTrait;
    use TranslatorTrait;

    /**
     * @var ApiAccessorInterface
     */
    private ApiAccessorInterface $accessor;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        ApiAccessorInterface $accessor,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->accessor = $accessor;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * @param PublishersList              $list
     * @param TokenInterface               $token
     * @param PublicationStrategyInterface $strategy
     *
     * @return int
     * @throws Exception
     */
    public function processPublishersList(
        PublishersList $list,
        TokenInterface $token,
        PublicationStrategyInterface $strategy
    ): int {
        if ($strategy->shouldProcessList($list)) {
            $eventRepository = $this->publishersListCollectedEventRepository;
            $memberCollection = $eventRepository->collectedPublishersList(
                $this->accessor,
                [
                    $eventRepository::OPTION_PUBLISHERS_LIST_ID => $list->id(),
                    $eventRepository::OPTION_PUBLISHERS_LIST_NAME => $list->name()
                ]
            );
            $memberCollection = $this->addOwnerToListOptionally(
                $memberCollection,
                $strategy
            );

            if ($memberCollection->isEmpty()) {
                EmptyListException::throws(
                    sprintf(
                        'List "%s" has no members',
                        $list->name()
                    )
                );
            }

            if ($memberCollection->isNotEmpty()) {
                $this->logger->info(
                    sprintf(
                        'About to publish messages for members in list "%s"',
                        $list->name()
                    )
                );
            }

            $publishedMessages = $this->processMemberOriginatingFromListWithToken(
                $memberCollection,
                $list,
                $token,
                $strategy
            );

            try {
                // Change token for each list unless there is only one single token available
                // Members lists can only be accessed by authenticated users owning the lists
                // See also https://dev.twitter.com/rest/reference/get/lists/ownerships
                $this->tokenChange->replaceAccessToken(
                    $token,
                    $this->accessor
                );
            } catch (CanNotReplaceAccessTokenException $exception) {
                // keep going with the current token
            }

            return $publishedMessages;
        }

        return 0;
    }

    private function processMemberOriginatingFromListWithToken(
        MemberCollectionInterface $members,
        PublishersList $list,
        TokenInterface $token,
        PublicationStrategyInterface $strategy
    ): int {
        $publishedMessages = 0;

        /** @var MemberIdentity $memberIdentity */
        foreach ($members->toArray() as $memberIdentity) {
            try {
                $publishedMessages += $this->memberIdentityProcessor->process(
                    $memberIdentity,
                    $strategy,
                    $token,
                    $list
                );
            } catch (ContinuePublicationException $exception) {
                $this->logger->info($exception->getMessage());

                continue;
            } catch (StopPublicationException $exception) {
                if ($exception->getPrevious() instanceof MembershipException) {
                    continue;
                }

                $this->logger->error($exception->getMessage());

                break;
            }  catch (Exception $exception) {
                $this->logger->error(
                    $exception->getMessage(),
                    [
                        'screen_name' => $memberIdentity->screenName(),
                        'stacktrace' => $exception->getTraceAsString()
                    ]
                );

                continue;
            }
        }

        return $publishedMessages;
    }

    private function addOwnerToListOptionally(
        MemberCollectionInterface $memberCollection,
        PublicationStrategyInterface $strategy
    ): MemberCollectionInterface
    {
        $members = $memberCollection->toArray();
        if ($strategy->shouldIncludeOwner()) {
            $eventRepository = $this->memberProfileCollectedEventRepository;
            $additionalMember = $eventRepository->collectedMemberProfile(
                $this->accessor,
                [$eventRepository::OPTION_SCREEN_NAME => $strategy->onBehalfOfWhom()]
            );
            array_unshift($members, $additionalMember);
            $strategy->willIncludeOwner(false);
        }

        return MemberCollection::fromArray($members);
    }
}