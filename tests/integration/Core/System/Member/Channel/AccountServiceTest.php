<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class AccountServiceTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    protected function setUp(): void
    {
        $this->accountService = static::getContainer()->get(AccountService::class);
    }

    public function testLoginById(): void
    {
        $channelContext = $this->createChannelContext();
        $memberId = $this->createMemberOfChannel($channelContext->getChannelId(), 'foo@bar.com');
        $token = $this->accountService->loginById($memberId, $channelContext);

        $member = $this->getMemberFromToken($token, $channelContext->getChannelId(), $memberId);

        static::assertSame('foo@bar.com', $member->getEmail());
        static::assertSame($memberId, $member->getId());
    }

    public function testLoginByCredentials(): void
    {
        $channelContext = $this->createChannelContext();
        $memberId = $this->createMemberOfChannel($channelContext->getChannelId(), 'foo@bar.com');
        $token = $this->accountService->loginByCredentials('foo@bar.com', 'contenaAdmin', $channelContext);

        $member = $this->getMemberFromToken($token, $channelContext->getChannelId(), $memberId);

        static::assertSame('foo@bar.com', $member->getEmail());
        static::assertSame($memberId, $member->getId());
    }

    public function testGetMemberByLogin(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext();
        $this->createMemberOfChannel($context->getChannelId(), $email);

        $member = $this->accountService->getMemberByLogin($email, 'contenaAdmin', $context);
        static::assertSame($email, $member->getEmail());
        static::assertSame($context->getChannelId(), $member->getChannelId());
    }

    public function testGetMemberByLoginWithInvalidPassword(): void
    {
        $this->expectException(BadCredentialsException::class);

        $email = 'johndoe@example.com';

        $context = $this->createChannelContext();
        $this->createMemberOfChannel($context->getChannelId(), $email);

        $member = $this->accountService->getMemberByLogin($email, 'invalid-password', $context);
        static::assertSame($email, $member->getEmail());
        static::assertSame($context->getChannelId(), $member->getChannelId());
    }

    public function testGetMemberByLoginWhenMembersHaveSameEmailReturnsTheLatestCreatedMember(): void
    {
        $idMember1 = Uuid::randomHex();
        $idMember2 = Uuid::randomHex();
        $email = 'johndoe@example.com';
        $context = $this->createChannelContext();

        $this->createMemberOfChannel($context->getChannelId(), $email, true, $idMember1, '2022-10-21 10:00:00');
        $this->cloneMemberWithDuplicateEmail($idMember1, $idMember2, '2022-10-22 10:00:00');

        $member = $this->accountService->getMemberByLogin($email, 'contenaAdmin', $context);
        static::assertSame($idMember2, $member->getId());
    }

    public function testGetMemberByLoginWhenMembersInDifferentChannelsHaveSameEmail(): void
    {
        $email = 'johndoe@example.com';

        $context1 = $this->createChannelContext();
        $this->createMemberOfChannel($context1->getChannelId(), $email);

        $context2 = $this->createChannelContext([
            'domains' => [
                [
                    'languageId' => $context1->getLanguageId(),
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ]);

        $this->createMemberOfChannel($context2->getChannelId(), $email);

        $member1 = $this->accountService->getMemberByLogin($email, 'contenaAdmin', $context1);

        static::assertSame($context1->getChannelId(), $member1->getChannelId());

        $member2 = $this->accountService->getMemberByLogin($email, 'contenaAdmin', $context2);
        static::assertSame($context2->getChannelId(), $member2->getChannelId());
    }

    public function testMemberFailsToLoginByMailWithInactiveAccount(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext();
        $this->createMemberOfChannel($context->getChannelId(), $email, false);

        $this->expectException(BadCredentialsException::class);
        $this->accountService->getMemberByLogin($email, 'contenaAdmin', $context);
    }

    private function getMemberFromToken(string $contextToken, string $channelId, string $memberId): MemberEntity
    {
        $channelContextService = static::getContainer()->get(ChannelContextService::class);
        $context = $channelContextService->get(
            new ChannelContextServiceParameters($channelId, $contextToken, memberId: $memberId)
        );

        $member = $context->getMember();
        static::assertNotNull($member);

        return $member;
    }

    private function createMemberOfChannel(
        string $channelId,
        string $email,
        bool $active = true,
        ?string $memberId = null,
        ?string $createdAt = null,
        ?string $password = TestDefaults::HASHED_PASSWORD,
    ): string {
        $memberId ??= Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'createdAt' => $createdAt,
            'memberNumber' => $memberId,
            'name' => 'Max Member',
            'email' => $email,
            'password' => $password,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'active' => $active,
        ];

        static::getContainer()
            ->get('member.repository')
            ->upsert([$member], Context::createDefaultContext());

        return $memberId;
    }

    private function cloneMemberWithDuplicateEmail(string $sourceMemberId, string $memberId, string $createdAt): void
    {
        $connection = static::getContainer()->get(Connection::class);
        /** @var list<array{Field: string, Extra: string}> $columns */
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `member`');

        $insertColumns = [];
        $selectExpressions = [];

        foreach ($columns as $column) {
            if (str_contains($column['Extra'], 'auto_increment')) {
                continue;
            }

            $field = $column['Field'];
            $insertColumns[] = '`' . $field . '`';
            $selectExpressions[] = match ($field) {
                'id' => ':memberId',
                'member_number' => ':memberNumber',
                'created_at' => ':createdAt',
                'updated_at' => 'NULL',
                default => '`' . $field . '`',
            };
        }

        // This test covers login behavior with legacy duplicate member rows that normal writes now reject.
        $connection->executeStatement(
            'INSERT INTO `member` (' . implode(', ', $insertColumns) . ')
             SELECT ' . implode(', ', $selectExpressions) . '
             FROM `member`
             WHERE `id` = :sourceMemberId',
            [
                'memberId' => Uuid::fromHexToBytes($memberId),
                'memberNumber' => $memberId,
                'createdAt' => $createdAt,
                'sourceMemberId' => Uuid::fromHexToBytes($sourceMemberId),
            ],
        );
    }
}
