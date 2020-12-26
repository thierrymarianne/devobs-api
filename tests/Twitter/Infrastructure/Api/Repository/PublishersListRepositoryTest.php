<?php
declare(strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Repository;

use App\Twitter\Infrastructure\Api\Repository\PublishersListRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException as DBALExceptionAlias;
use Doctrine\DBAL\ParameterType;
use Faker\Factory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group repository_publishers_list
 */
class PublishersListRepositoryTest extends KernelTestCase
{
    private const EXPECTED_TOTAL_STATUS_PUBLISHED = 10;

    private Connection $connection;

    /**
     * @test
     *
     * @throws
     */
    public function it_should_update_the_total_status(): void
    {
        $publishersListId = $this->publishPublishersListHavingStatus();

        $publishersListRepository = self::$container->get(PublishersListRepository::class);
        $publishersList = $publishersListRepository->findOneBy(['id' => $publishersListId]);

        $expectedTotalStatusPostUpdate = self::EXPECTED_TOTAL_STATUS_PUBLISHED;
        $result = $publishersListRepository->updateTotalStatuses(
            [
                'totalStatuses' => -1,
                'id' => $publishersListId
            ],
            $publishersList
        );

        $entityManager = self::$container->get('doctrine.orm.default_entity_manager');
        $entityManager->flush();

        self::assertEquals($expectedTotalStatusPostUpdate, $result['totalStatuses']);

        $statement = $this->connection->executeQuery('
            SELECT total_statuses AS total_status
            FROM publishers_list    
            WHERE id = '.$publishersListId.'
        ');
        $result = $statement->fetchAssociative();

        self::assertEquals($expectedTotalStatusPostUpdate, $result['total_status']);
    }

    protected function setUp(): void
    {
        self::$kernel    = self::bootKernel();
        self::$container = self::$kernel->getContainer();

        $this->connection = self::$container->get('doctrine.dbal.default_connection');

        $this->tearDownFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixtures();

        parent::tearDown();
    }

    private function preparePublishersList(): int
    {
        $insertPublishersList = <<<QUERY
            INSERT INTO publishers_list (
                screen_name,
                name,
                locked,
                locked_at,
                created_at
            ) VALUES (
                'New York Times',
                'press review',
                true,
                null,
                NOW()
            )
QUERY;
        $this->connection->executeQuery($insertPublishersList);

        $statement = $this->connection->executeQuery(
            '
            SELECT id as publication_id
            FROM publishers_list
        '
        );
        $result    = $statement->fetch();

        return (int) $result['publication_id'];
    }

    private function publishPublishersListHavingStatus(): int
    {
        $this->publishStatus();

        $statement = $this->connection->executeQuery(
            '
            SELECT ust_id as status_id
            FROM weaving_status
        '
        );
        $results   = $statement->fetchAllAssociative();

        $publishersListId = $this->preparePublishersList();

        $queryParams = implode(
            ',',
            array_reduce(
                $results,
                function ($accumulator, $status) use ($publishersListId) {
                    $accumulator[] = '(' . $status['status_id'] . ' ' . ', ' . $publishersListId . ')';

                    return $accumulator;
                },
                []
            )
        );

        $insertPublishersListStatus = <<<QUERY
            INSERT INTO weaving_status_aggregate
            (status_id, aggregate_id) VALUES $queryParams
QUERY;

        $this->connection->executeQuery($insertPublishersListStatus);

        return (int) $publishersListId;
    }

    /**
     * @throws DBALExceptionAlias
     */
    private function publishStatus(): void
    {
        $this->tearDownFixtures();

        $insertStatus = <<<QUERY
            INSERT INTO weaving_status (
                ust_hash,
                ust_full_name,
                ust_name,
                ust_text,
                ust_avatar,
                ust_access_token,
                ust_status_id,
                ust_api_document,
                ust_starred,
                ust_indexed,
                ust_created_at
            )
            VALUES 
QUERY;

        $faker = Factory::create();

        $placeholders = [];
        $statusParams = [];
        $paramsTypes  = [];

        foreach (range(1, self::EXPECTED_TOTAL_STATUS_PUBLISHED) as $statusIndex => $item) {

            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $firstName = $faker->firstName;
            $lastName  = $faker->lastName;

            $statusParams[] = sha1((string) random_int(0, 1000000));
            $statusParams[] = $firstName . ' ' . $lastName;
            $statusParams[] = $firstName;
            $statusParams[] = 'Publication #' . $statusIndex;
            $statusParams[] = 'https://gravatar/member-' . $statusIndex;
            $statusParams[] = '21039383-wpoqlalmckjd';
            $statusParams[] = '{}';
            $statusParams[] = '12121029493330434' . $statusIndex;
            $statusParams[] = true;
            $statusParams[] = true;
            $statusParams[] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::STRING;
            $paramsTypes[] = ParameterType::BOOLEAN;
            $paramsTypes[] = ParameterType::BOOLEAN;
            $paramsTypes[] = ParameterType::STRING;
        }

        $this->connection->executeQuery(
            $insertStatus . implode(',', $placeholders),
            $statusParams,
            $paramsTypes
        );
    }

    private function tearDownFixtures(): void
    {
        $this->connection->executeQuery(
            'DELETE FROM weaving_status_aggregate'
        );

        $this->connection->executeQuery(
            'DELETE FROM timely_status'
        );

        $this->connection->executeQuery(
            'DELETE FROM weaving_status'
        );
    }
}
