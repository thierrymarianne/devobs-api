<?php

namespace App\Member\Command;

use App\Accessor\Exception\ReadOnlyApplicationException;
use App\Console\AbstractCommand;
use App\Member\Repository\AggregateSubscriptionRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SubscribeToMemberTimelinesCommand extends AbstractCommand
{
    const OPTION_AGGREGATE_NAME = 'aggregate-name';

    const OPTION_MEMBER_NAME = 'member-name';

    /**
     * @var AggregateSubscriptionRepository
     */
    public $aggregateSubscriptionRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $logger;

    public function configure()
    {
        $this->setName('subscribe-to-member-timelines')
            ->setDescription('Subscribe to member timelines.')
            ->addOption(
                self::OPTION_AGGREGATE_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The name of an aggregate'
            )
            ->addOption(
                self::OPTION_MEMBER_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The name of a member'
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $memberName = $this->input->getOption(self::OPTION_MEMBER_NAME);
        $aggregateName = $this->input->getOption(self::OPTION_AGGREGATE_NAME);

        try {
            $this->aggregateSubscriptionRepository->letMemberSubscribeToAggregate(
                $memberName,
                $aggregateName
            );
        } catch (ReadOnlyApplicationException $exception) {
            $this->logger->critical($exception->getMessage());
            $this->output->writeln($exception->getMessage());

            return self::RETURN_STATUS_FAILURE;
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());

            return self::RETURN_STATUS_FAILURE;
        }

        $this->output->writeln(sprintf(
            'Member with name "%s" is not a subscriber of an aggregate with name "%s"',
            $memberName,
            $aggregateName
        ));

        return self::RETURN_STATUS_SUCCESS;
    }
}
