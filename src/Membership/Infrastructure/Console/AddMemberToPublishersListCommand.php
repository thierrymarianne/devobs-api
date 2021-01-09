<?php

namespace App\Membership\Infrastructure\Console;

use App\AbstractCommand;
use App\Membership\Domain\Model\MemberInterface;
use App\Membership\Infrastructure\Entity\AggregateSubscription;
use App\Membership\Infrastructure\Repository\AggregateSubscriptionRepository;
use App\Twitter\Domain\Api\Accessor\MembersListAccessorInterface;
use App\Twitter\Domain\Api\Accessor\OwnershipAccessorInterface;
use App\Twitter\Domain\Api\Accessor\StatusAccessorInterface;
use App\Twitter\Domain\Membership\Repository\MemberRepositoryInterface;
use App\Twitter\Domain\Publication\Repository\PublishersListRepositoryInterface;
use App\Twitter\Domain\Resource\PublishersList;
use App\Twitter\Infrastructure\Api\Selector\MemberOwnershipsBatchSelector;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddMemberToPublishersListCommand extends AbstractCommand
{
    public const COMMAND_NAME = 'membership:add-members-to-aggregate';

    public const OPTION_PUBLISHERS_LIST_NAME = 'publishers_list_name';

    public const OPTION_LIST_NAME = 'list';

    public const OPTION_MEMBER_LIST = 'member_list';

    public const ARGUMENT_SCREEN_NAME = 'screen_name';

    private MembersListAccessorInterface $membersListAccessor;

    private OwnershipAccessorInterface $ownershipAccessor;

    private PublishersListRepositoryInterface $publishersListRepository;

    private AggregateSubscriptionRepository $aggregateSubscriptionRepository;

    private MemberRepositoryInterface $memberRepository;

    private StatusAccessorInterface $statusAccessor;

    private LoggerInterface $logger;

    public function __construct(
        string $name,
        AggregateSubscriptionRepository $aggregateSubscriptionRepository,
        MemberRepositoryInterface $memberRepository,
        PublishersListRepositoryInterface $publishersListRepository,
        MembersListAccessorInterface $membersListAccessor,
        OwnershipAccessorInterface $ownershipAccessor,
        StatusAccessorInterface $statusAccessor,
        LoggerInterface $logger
    ) {
        $this->aggregateSubscriptionRepository = $aggregateSubscriptionRepository;
        $this->memberRepository = $memberRepository;
        $this->publishersListRepository = $publishersListRepository;

        $this->membersListAccessor = $membersListAccessor;
        $this->ownershipAccessor = $ownershipAccessor;
        $this->statusAccessor = $statusAccessor;

        $this->logger = $logger;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription('Add a member list to a publisher list.')
            ->addArgument(
                self::ARGUMENT_SCREEN_NAME,
                InputArgument::REQUIRED,
                'The screen name of a member owning lists'
            )
            ->addOption(
                self::OPTION_MEMBER_LIST,
                null,
                InputOption::VALUE_OPTIONAL,
                'A comma-separated list of member screen names'
            )->addOption(
                self::OPTION_LIST_NAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'The optional name of a list containing members to be added to a publishers list'
            )
            ->addOption(
                self::OPTION_PUBLISHERS_LIST_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The name of a publishers list'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        if ($this->providedWithInvalidOptions()) {
            $errorMessage = 'No list name, nor screen name has been provided.';
            $this->logger->critical($errorMessage);
            $this->output->writeln($errorMessage);

            return self::FAILURE;
        }

        try {
            $this->addMembersToList($this->findListToWhichMembersShouldBeAddedTo());
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->output->writeln($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function findListToWhichMembersShouldBeAddedTo(): PublishersList
    {
        $screenName = $this->input->getArgument(self::ARGUMENT_SCREEN_NAME);
        $ownershipsLists = $this->ownershipAccessor->getMemberOwnerships(
            new MemberOwnershipsBatchSelector($screenName)
        );

        $publishersListName = $this->input->getOption(self::OPTION_PUBLISHERS_LIST_NAME);
        $filteredLists = array_filter(
            $ownershipsLists->toArray(),
            static function (PublishersList $list) use ($publishersListName) {
                return $list->name() === $publishersListName;
            }
        );

        if (count($filteredLists) !== 1) {
            throw new LogicException('There should be exactly one remaining list.');
        }

        return array_pop($filteredLists);
    }

    private function addMembersToList(PublishersList $targetList): void {
        $membersList = $this->getListOfMembers();

        $this->membersListAccessor->addMembersToList($membersList, $targetList->id());
        $members = $this->ensureMembersExist($membersList);

        array_walk(
            $members,
            function (MemberInterface $member) use ($targetList) {
                $this->publishersListRepository->addMemberToList($member, $targetList);
            }
        );

        $this->output->writeln('All members have been successfully added to the publishers lists.');
    }

    /**
     * @param $memberList
     * @return array
     */
    private function ensureMembersExist($memberList): array
    {
        return array_map(
            function (string $memberName) {
                $member = $this->memberRepository->findOneBy(['twitter_username' => $memberName]);
                if (!($member instanceof MemberInterface)) {
                    $member = $this->statusAccessor->ensureMemberHavingNameExists($memberName);
                }

                return $member;
            },
            $memberList
        );
    }

    private function getListOfMembers(): array
    {
        if ($this->input->hasOption(self::OPTION_LIST_NAME) &&
            $this->input->getOption(self::OPTION_LIST_NAME)) {

            try {
                $subscriptions = $this->aggregateSubscriptionRepository->findSubscriptionsByAggregateName(
                    $this->input->getOption(self::OPTION_LIST_NAME)
                );
            } catch (\Exception $exception) {
                $this->logger->critical($exception->getMessage());
                $this->output->writeln($exception->getMessage());

                return [];
            }

            $memberList = [];
            array_walk(
                $subscriptions,
                static function (AggregateSubscription $subscription) use (&$memberList) {
                    $memberName = $subscription->subscription->twitterScreenName();
                    $memberList[] = $memberName;
                }
            );

            return $memberList;
        }

        return explode(',', $this->input->getOption(self::OPTION_MEMBER_LIST));
    }

    /**
     * @return bool
     */
    private function providedWithInvalidOptions(): bool
    {
        return (!$this->input->hasArgument(self::ARGUMENT_SCREEN_NAME) &&
                !$this->input->hasOption(self::OPTION_LIST_NAME)) ||
            (!$this->input->getOption(self::OPTION_LIST_NAME) &&
                !$this->input->getArgument(self::ARGUMENT_SCREEN_NAME));
    }
}
