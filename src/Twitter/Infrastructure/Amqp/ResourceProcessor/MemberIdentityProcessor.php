<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Amqp\ResourceProcessor;

use App\Twitter\Infrastructure\PublishersList\AggregateAwareTrait;
use App\Twitter\Infrastructure\Amqp\Exception\SkippableMemberException;
use App\Twitter\Domain\Api\Model\TokenInterface;
use App\Twitter\Domain\Curation\PublicationStrategyInterface;
use App\Twitter\Domain\Membership\Exception\MembershipException;
use App\Twitter\Domain\Membership\MemberFacingStrategy;
use App\Twitter\Domain\Resource\MemberIdentity;
use App\Twitter\Domain\Resource\PublishersList;
use App\Twitter\Infrastructure\Amqp\Exception\ContinuePublicationException;
use App\Twitter\Infrastructure\Amqp\Exception\StopPublicationException;
use App\Twitter\Infrastructure\Amqp\Message\FetchMemberStatus;
use App\Twitter\Infrastructure\DependencyInjection\Membership\MemberProfileAccessorTrait;
use App\Twitter\Domain\Publication\Repository\PublishersListRepositoryInterface;
use App\Twitter\Domain\Api\Accessor\MemberProfileAccessorInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use function sprintf;

class MemberIdentityProcessor implements MemberIdentityProcessorInterface
{
    use AggregateAwareTrait;
    use MemberProfileAccessorTrait;

    /**
     * @var MessageBusInterface
     */
    private MessageBusInterface $dispatcher;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PublishersListRepositoryInterface
     */
    private PublishersListRepositoryInterface $aggregateRepository;

    public function __construct(
        MessageBusInterface $dispatcher,
        MemberProfileAccessorInterface $memberProfileAccessor,
        PublishersListRepositoryInterface $aggregateRepository,
        LoggerInterface $logger
    ) {
        $this->dispatcher                 = $dispatcher;
        $this->aggregateRepository        = $aggregateRepository;
        $this->memberProfileAccessor      = $memberProfileAccessor;
        $this->logger                     = $logger;
    }

    /**
     * @param MemberIdentity               $memberIdentity
     * @param PublicationStrategyInterface $strategy
     * @param TokenInterface               $token
     * @param PublishersList              $list
     *
     * @return int
     * @throws ContinuePublicationException
     * @throws MembershipException
     * @throws StopPublicationException
     */
    public function process(
        MemberIdentity $memberIdentity,
        PublicationStrategyInterface $strategy,
        TokenInterface $token,
        PublishersList $list
    ): int {
        try {
            $this->dispatchPublications($memberIdentity, $strategy, $token, $list);

            return 1;
        } catch (SkippableMemberException $exception) {
            $this->logger->info($exception->getMessage());

            return 0;
        } catch (MembershipException $exception) {
            if (MemberFacingStrategy::shouldBreakPublication($exception)) {
                $this->logger->info($exception->getMessage());

                StopPublicationException::throws($exception->getMessage(), $exception);
            }

            if (MemberFacingStrategy::shouldContinuePublication($exception)) {
                ContinuePublicationException::throws($exception->getMessage(), $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param MemberIdentity $memberIdentity
     * @param PublicationStrategyInterface $strategy
     * @param TokenInterface $token
     * @param PublishersList $list
     * @throws SkippableMemberException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function dispatchPublications(
        MemberIdentity $memberIdentity,
        PublicationStrategyInterface $strategy,
        TokenInterface $token,
        PublishersList $list
    ): void {
        $this->skipUnrestrictedMember($memberIdentity, $strategy);

        $member = $this->memberProfileAccessor->getMemberByIdentity(
            $memberIdentity
        );

        $strategy->guardAgainstWhisperingMember($member, $memberIdentity);

        MemberFacingStrategy::guardAgainstProtectedMember($member, $memberIdentity);
        MemberFacingStrategy::guardAgainstSuspendedMember($member, $memberIdentity);

        $FetchMemberStatus = FetchMemberStatus::makeMemberIdentityCard(
            $this->aggregateRepository->byName(
                $member->twitterScreenName(),
                $list->name(),
                $list->id()
            ),
            $token,
            $member,
            $strategy->dateBeforeWhichPublicationsAreCollected()
        );

        $this->dispatcher->dispatch($FetchMemberStatus);
    }

    /**
     * @param MemberIdentity               $memberIdentity
     * @param PublicationStrategyInterface $strategy
     *
     * @throws SkippableMemberException
     */
    private function skipUnrestrictedMember(
        MemberIdentity $memberIdentity,
        PublicationStrategyInterface $strategy
    ): void {
        if ($strategy->restrictDispatchToSpecificMember($memberIdentity)) {
            throw new SkippableMemberException(
                sprintf(
                    'Skipping "%s" as member restriction applies',
                    $memberIdentity->screenName()
                )
            );
        }
    }
}