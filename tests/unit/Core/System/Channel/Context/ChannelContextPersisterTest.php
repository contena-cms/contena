<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ChannelContextPersister::class)]
class ChannelContextPersisterTest extends TestCase
{
    private ChannelContextPersister $contextPersister;

    private Stub&Result $statement;

    protected function setUp(): void
    {
        $this->statement = static::createStub(Result::class);

        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($this->statement);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->contextPersister = new ChannelContextPersister(
            $connection,
            static::createStub(EventDispatcherInterface::class),
            new NativeClock(),
            'P1D', // 1 day expiration is the default value
        );
    }

    public function testLoadWithNoContextFoundReturnsEmptyArray(): void
    {
        // Simulate no context found in the database
        $this->statement->method('fetchAllAssociative')->willReturn([]);

        $result = $this->contextPersister->load(Random::getAlphanumericString(32), TestDefaults::CHANNEL, Uuid::randomHex());
        static::assertSame([], $result);
    }

    /**
     * @param array<string, string> $payload
     * @param array<string, string|bool> $expected
     */
    #[DataProvider('tokenExpirationDataProvider')]
    public function testLoadContextAgainstTokenExpiration(string $token, ?string $memberId, \DateTimeImmutable $updatedAt, array $payload, array $expected): void
    {
        $this->statement->method('fetchAllAssociative')->willReturn([
            [
                'updated_at' => $updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'payload' => \json_encode($payload, \JSON_THROW_ON_ERROR),
                'token' => $token,
            ],
        ]);

        $result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $memberId);

        static::assertSame($expected, $result);
    }

    public static function tokenExpirationDataProvider(): \Generator
    {
        $token = Random::getAlphanumericString(32);
        $memberId = Uuid::randomHex();
        $updatedAt = new \DateTimeImmutable();
        // When we expire the token, we set it to 2 days ago, as there is 1 day expiration

        yield 'it keeps payload when memberId is provided and token is expired' => [
            'token' => $token,
            'memberId' => $memberId,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => true, 'token' => $token],
        ];
        yield 'it withdraws payload when memberId is not provided and token is expired' => [
            'token' => $token,
            'memberId' => null,
            'updatedAt' => $updatedAt->sub(new \DateInterval('P2D')),
            'payload' => ['a_key' => 'aValue', 'anotherKey' => 'anotherValue'],
            'expected' => ['expired' => true, 'token' => $token],
        ];

        yield 'it keeps payload when memberId is not provided and token is not expired' => [
            'token' => $token,
            'memberId' => null,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'token' => $token],
        ];
        yield 'it keeps payload when memberId is provided and token is not expired' => [
            'token' => $token,
            'memberId' => $memberId,
            'updatedAt' => $updatedAt,
            'payload' => ['a_key' => 'aValue'],
            'expected' => ['a_key' => 'aValue', 'expired' => false, 'token' => $token],
        ];
    }
}
