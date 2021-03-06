<?php
declare(strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Accessor;

use App\Twitter\Domain\Curation\Repository\OwnershipBatchCollectedEventRepositoryInterface;
use App\Twitter\Domain\Resource\MemberOwnerships;
use App\Twitter\Domain\Api\AccessToken\Repository\TokenRepositoryInterface;
use App\Twitter\Infrastructure\Api\AccessToken\TokenChangeInterface;
use App\Twitter\Infrastructure\Api\Entity\Token;
use App\Twitter\Infrastructure\Api\Exception\InvalidSerializedTokenException;
use App\Twitter\Infrastructure\Curation\Repository\OwnershipBatchCollectedEventRepository;
use App\Twitter\Infrastructure\Api\Accessor\OwnershipAccessor;
use App\Tests\Twitter\Infrastructure\Api\AccessToken\Builder\Entity\TokenChangeBuilder;
use App\Tests\Twitter\Infrastructure\Api\AccessToken\Builder\Repository\TokenRepositoryBuilder;
use App\Tests\Twitter\Infrastructure\Api\Builder\Accessor\ApiAccessorBuilder;
use App\Twitter\Infrastructure\Exception\OverCapacityException;
use App\Twitter\Infrastructure\Api\Selector\AuthenticatedSelector;
use Exception;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group   ownership
 */
class OwnershipAccessorTest extends KernelTestCase
{
    private const MEMBER_SCREEN_NAME = 'mcurie';
    private const TOKEN              = 'token';
    private const SECRET             = 'secret';
    private const REPLACEMENT_TOKEN  = 'replacement-token';
    private const REPLACEMENT_SECRET = 'replacement-secret';

    private OwnershipBatchCollectedEventRepositoryInterface $eventRepository;

    protected function setUp(): void
    {
        self::$kernel = self::bootKernel();
        self::$container = self::$kernel->getContainer();

        $this->eventRepository = self::$container->get('test.'.OwnershipBatchCollectedEventRepository::class);
    }

    /**
     * @throws
     *
     * @test
     */
    public function it_gets_member_ownerships(): void
    {
        // Arrange

        $builder  = new ApiAccessorBuilder();
        $ownershipCollection = $builder->makeOwnershipCollection();
        $accessor = $builder->willGetOwnershipCollectionForMember($ownershipCollection)
            ->build();

        $ownershipAccessor = new OwnershipAccessor(
            $accessor,
            $this->makeTokenRepository(1),
            $this->makeTokenChange(),
            new NullLogger()
        );

        $ownershipAccessor->setOwnershipBatchCollectedEventRepository(
            $this->eventRepository
        );

        $activeToken = $this->getActiveToken();

        // Act

        $ownerships = $ownershipAccessor->getOwnershipsForMemberHavingScreenNameAndToken(
            new AuthenticatedSelector(
                $activeToken,
                self::MEMBER_SCREEN_NAME
            )
        );

        // Assert

        self::assertEquals($activeToken, $ownerships->token());
        self::assertEquals($ownershipCollection, $ownerships->ownershipCollection());
    }

    /**
     * @throws
     *
     * @test
     */
    public function it_gets_member_ownerships_from_a_secondary_set_of_tokens(): void
    {
        // Arrange

        $builder  = new ApiAccessorBuilder();
        $ownershipCollection = $builder->makeOwnershipCollection();
        $accessor = $builder->willGetOwnershipCollectionAfterThrowingForMember(
            $ownershipCollection,
        )->build();

        $ownershipAccessor = new OwnershipAccessor(
            $accessor,
            $this->makeTokenRepository(2),
            $this->makeTokenChange(),
            new NullLogger()
        );

        $ownershipAccessor->setOwnershipBatchCollectedEventRepository(
            $this->eventRepository
        );

        $activeToken = $this->getActiveToken();

        // Act

        $ownerships = $ownershipAccessor->getOwnershipsForMemberHavingScreenNameAndToken(
            new AuthenticatedSelector(
                $activeToken,
                self::MEMBER_SCREEN_NAME
            )
        );

        // Assert

        $replacementToken = Token::fromArray(
            [
                'token' => self::REPLACEMENT_TOKEN,
                'secret' => self::REPLACEMENT_SECRET,
            ]
        );

        self::assertEquals($replacementToken, $ownerships->token());
        self::assertEquals($ownershipCollection, $ownerships->ownershipCollection());
    }

    /**
     * @test
     *
     * @throws
     */
    public function it_can_not_get_member_ownerships(): void
    {
        // Arrange

        $builder  = new ApiAccessorBuilder();
        $accessor = $builder->willThrowWhenGettingOwnershipCollectionForMember()
            ->build();

        $ownershipAccessor = new OwnershipAccessor(
            $accessor,
            $this->makeTokenRepository(1),
            $this->makeTokenChange(),
            new NullLogger()
        );

        $ownershipAccessor->setOwnershipBatchCollectedEventRepository(
            $this->eventRepository
        );

        try {
            // Act

            $ownershipAccessor->getOwnershipsForMemberHavingScreenNameAndToken(
                new AuthenticatedSelector(
                    $this->getActiveToken(),
                    self::MEMBER_SCREEN_NAME
                )
            );
        } catch (Exception $exception) {
            self::assertInstanceOf(
                OverCapacityException::class,
                $exception
            );

            return;
        }

        self::fail('There should be a exception raised');
    }

    /**
     * @return Token
     * @throws InvalidSerializedTokenException
     */
    private function getActiveToken(): Token
    {
        return Token::fromArray(
            [
                'token'  => self::TOKEN,
                'secret' => self::SECRET,
            ]
        );
    }

    private function makeTokenChange(): TokenChangeInterface
    {
        $tokenChangeBuilder = new TokenChangeBuilder();
        $tokenChangeBuilder = $tokenChangeBuilder->willReplaceAccessToken(
            Token::fromArray(
                [
                    'token'  => self::REPLACEMENT_TOKEN,
                    'secret' => self::REPLACEMENT_SECRET,
                ]
            )
        );

        return $tokenChangeBuilder->build();
    }

    /**
     * @param int $totalUnfrozenTokens
     *
     * @return TokenRepositoryInterface
     */
    private function makeTokenRepository(int $totalUnfrozenTokens): TokenRepositoryInterface
    {
        $tokenRepositoryBuilder = new TokenRepositoryBuilder();
        $tokenRepositoryBuilder->willReturnTheCountOfUnfrozenTokens($totalUnfrozenTokens);

        return $tokenRepositoryBuilder->build();
    }
}