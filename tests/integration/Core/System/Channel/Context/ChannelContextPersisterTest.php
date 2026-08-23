<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class ChannelContextPersisterTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private Connection $connection;

    private ChannelContextPersister $contextPersister;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->contextPersister = new ChannelContextPersister(
            $this->connection,
            new EventDispatcher(),
            new NativeClock(),
        );
    }

    public function testLoad(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
        ];

        $this->insertContext($token, TestDefaults::CHANNEL, $expected);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testLoadByMemberId(): void
    {
        $token = Uuid::randomHex();
        $memberId = $this->createMember();
        $this->contextPersister->save($token, [], TestDefaults::CHANNEL, $memberId);

        static::assertNotEmpty($result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $memberId));
        static::assertSame($token, $result['token']);
    }

    public function testLoadNotExisting(): void
    {
        static::assertSame([], $this->contextPersister->load(Random::getAlphanumericString(32), TestDefaults::CHANNEL));
    }

    public function testLoadMemberNotExisting(): void
    {
        static::assertSame([], $this->contextPersister->load(Random::getAlphanumericString(32), TestDefaults::CHANNEL, Uuid::randomHex()));
    }

    public function testLoadKeepsPayloadWhenTokenExpiresAndMemberIdIsProvided(): void
    {
        $token = Random::getAlphanumericString(32);
        $memberId = $this->createMember();
        $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'expired' => false,
            'token' => $token,
        ];

        $this->insertContext($token, TestDefaults::CHANNEL, $payload, new \DateTimeImmutable('-2 days'), $memberId);

        $payload['expired'] = true;
        ksort($payload);
        $result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $memberId);
        ksort($result);
        static::assertSame($payload, $result);
    }

    public function testLoadWithdrawsPayloadWhenTokenExpiresAndMemberIdIsNotProvided(): void
    {
        $token = Random::getAlphanumericString(32);
        $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'expired' => false,
            'token' => $token,
        ];

        $this->insertContext($token, TestDefaults::CHANNEL, $payload, new \DateTimeImmutable('-2 days'));

        static::assertSame(['expired' => true, 'token' => $token], $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testSaveWithoutExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
            'expired' => false,
            'token' => $token,
        ];

        $this->contextPersister->save($token, $expected, TestDefaults::CHANNEL);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testSaveNewMemberContextWithoutExistingMemberContext(): void
    {
        $token = Random::getAlphanumericString(32);
        $memberId = $this->createMember();
        $expected = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
        ];

        $this->contextPersister->save($token, $expected, TestDefaults::CHANNEL, $memberId);

        $actual = $this->contextPersister->load($token, TestDefaults::CHANNEL, $memberId);
        ksort($actual);
        ksort($expected);
        static::assertSame($expected, $actual);
    }

    public function testSaveMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);
        $this->insertContext($token, TestDefaults::CHANNEL, [
            'first' => 'test',
            'second' => 'second test',
        ]);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ], TestDefaults::CHANNEL);

        $actual = $this->contextPersister->load($token, TestDefaults::CHANNEL);
        ksort($actual);

        static::assertSame([
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ], $actual);
    }

    public function testSaveMemberContextMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);
        $memberId = $this->createMember();
        $this->insertContext($token, TestDefaults::CHANNEL, [
            'first' => 'test',
            'second' => 'second test',
        ], null, $memberId);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ], TestDefaults::CHANNEL, $memberId);

        $actual = $this->contextPersister->load($token, TestDefaults::CHANNEL, $memberId);
        ksort($actual);

        static::assertSame([
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ], $actual);
    }

    public function testReplaceWithoutExistingContext(): void
    {
        $context = Generator::generateChannelContext(token: 'old-token');

        $newToken = $this->contextPersister->replace('old-token', $context);

        static::assertNotSame('old-token', $newToken);
        static::assertSame($newToken, $context->getToken());
        static::assertSame($newToken, $this->connection->fetchOne('SELECT token FROM channel_api_context'));
    }

    public function testReplaceWithExistingContext(): void
    {
        $this->insertContext('old-token', TestDefaults::CHANNEL, []);
        $context = Generator::generateChannelContext(token: 'old-token');

        $newToken = $this->contextPersister->replace('old-token', $context);

        static::assertNotSame('old-token', $newToken);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM channel_api_context WHERE token = :token', ['token' => 'old-token']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM channel_api_context WHERE token = :token', ['token' => $newToken]));
    }

    public function testMemberIdColumnIsBeingUsed(): void
    {
        $memberId = $this->createMember();
        $token = Random::getAlphanumericString(32);

        $this->contextPersister->save($token, [], TestDefaults::CHANNEL, $memberId);

        static::assertSame($memberId, $this->connection->fetchOne(
            'SELECT LOWER(HEX(member_id)) FROM channel_api_context WHERE token = :token',
            ['token' => $token]
        ));
    }

    public function testRevokeMemberTokensPreservesRequestedToken(): void
    {
        $memberId = $this->createMember();
        $preservedToken = Random::getAlphanumericString(32);
        $revokedToken = Random::getAlphanumericString(32);

        $this->insertContext($preservedToken, TestDefaults::CHANNEL, ['memberId' => $memberId], null, $memberId);
        $this->insertContext($revokedToken, TestDefaults::CHANNEL, ['memberId' => $memberId], null, $memberId);

        $this->contextPersister->revokeAllMemberTokens($memberId, $preservedToken);

        static::assertSame($memberId, $this->connection->fetchOne(
            'SELECT LOWER(HEX(member_id)) FROM channel_api_context WHERE token = :token',
            ['token' => $preservedToken]
        ));
        static::assertNull($this->connection->fetchOne(
            'SELECT member_id FROM channel_api_context WHERE token = :token',
            ['token' => $revokedToken]
        ));
        static::assertSame(['memberId' => null], json_decode((string) $this->connection->fetchOne(
            'SELECT payload FROM channel_api_context WHERE token = :token',
            ['token' => $revokedToken]
        ), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDeleteCannotRemoveAnotherTenantChannelContext(): void
    {
        [$channelA, $channelB] = $this->createTenantChannelContexts();
        $token = Random::getAlphanumericString(32);
        $this->insertContext($token, $channelB->getChannelId(), ['scope' => 'tenant-b']);

        $this->contextPersister->delete($token, $channelA->getChannelId());

        static::assertSame(
            'tenant-b',
            $this->contextPersister->load($token, $channelB->getChannelId())['scope'] ?? null,
        );
    }

    public function testSaveRejectsAnotherTenantChannelToken(): void
    {
        [$channelA, $channelB] = $this->createTenantChannelContexts();
        $token = Random::getAlphanumericString(32);
        $this->insertContext($token, $channelB->getChannelId(), ['scope' => 'tenant-b']);

        $this->expectExceptionObject(ChannelException::contextTokenScopeMismatch());

        $this->contextPersister->save($token, ['scope' => 'tenant-a'], $channelA->getChannelId());
    }

    public function testReplaceRejectsAnotherTenantChannelToken(): void
    {
        [$channelA, $channelB] = $this->createTenantChannelContexts();
        $token = Random::getAlphanumericString(32);
        $this->insertContext($token, $channelB->getChannelId(), ['scope' => 'tenant-b']);

        $this->expectExceptionObject(ChannelException::contextTokenScopeMismatch());

        $this->contextPersister->replace($token, $channelA);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertContext(
        string $token,
        string $channelId,
        array $payload,
        ?\DateTimeInterface $updatedAt = null,
        ?string $memberId = null,
    ): void {
        $payload['token'] ??= $token;
        $payload['expired'] ??= false;

        $data = [
            'token' => $token,
            'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes($channelId),
            'member_id' => $memberId !== null ? Uuid::fromHexToBytes($memberId) : null,
        ];

        $data['updated_at'] = ($updatedAt ?? new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('channel_api_context', $data);
    }

    private function createMember(): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('member.repository')->create([[
            'id' => $id,
            'name' => 'Persister member',
            'memberNumber' => Uuid::randomHex(),
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array{ChannelContext, ChannelContext}
     */
    private function createTenantChannelContexts(): array
    {
        $contextA = $this->createTenantContext($this->createTenant('Context persister tenant A'));
        $contextB = $this->createTenantContext($this->createTenant('Context persister tenant B'));
        $channelA = $this->createChannel(['name' => 'Context persister channel A'], $contextA);
        $channelB = $this->createChannel(['name' => 'Context persister channel B'], $contextB);
        $factory = static::getContainer()->get(ChannelContextFactory::class);

        return [
            $factory->create(Random::getAlphanumericString(32), $channelA['id']),
            $factory->create(Random::getAlphanumericString(32), $channelB['id']),
        ];
    }
}
