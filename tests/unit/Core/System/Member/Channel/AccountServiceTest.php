<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\MemberContextRestorer;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Event\MemberBeforeLoginEvent;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\Exception\MemberNotFoundByIdException;
use Contena\Core\System\Member\Exception\MemberOptinNotCompletedException;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * @internal
 */
#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $context = $this->createContext();
        $member = $this->createMember($context->getChannelId());
        $repository = $this->createRepository($member, $context);
        $loggedInContext = $this->createContext($member, 'new-token');

        $restorer = $this->createMock(MemberContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restore')
            ->with($member->getId(), $context)
            ->willReturn($loggedInContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MemberBeforeLoginEvent::class, static function (MemberBeforeLoginEvent $event) use ($context, &$beforeLoginEventCalled): void {
            $beforeLoginEventCalled = true;
            static::assertSame('foo@bar.de', $event->getEmail());
            static::assertSame($context, $event->getChannelContext());
        });
        $eventDispatcher->addListener(MemberLoginEvent::class, static function (MemberLoginEvent $event) use ($member, $loggedInContext, &$loginEventCalled): void {
            $loginEventCalled = true;
            static::assertSame($member, $event->getMember());
            static::assertSame($loggedInContext, $event->getChannelContext());
            static::assertSame('new-token', $event->getContextToken());
        });

        $accountService = new AccountService(
            $repository,
            $eventDispatcher,
            $restorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        static::assertSame('new-token', $accountService->loginByCredentials('foo@bar.de', 'contenaAdmin', $context));
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $repository->updates);
        static::assertSame($member->getId(), $repository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $repository->updates[0][0]['lastLogin']);
    }

    public function testLoginFailsByInvalidCredentials(): void
    {
        $context = $this->createContext();
        $member = $this->createMember($context->getChannelId());

        $accountService = $this->createAccountService($this->createRepository($member, $context));

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'invalid-password', $context);
    }

    public function testLoginHidesUnknownEmailBehindBadCredentials(): void
    {
        $context = $this->createContext();
        $accountService = $this->createAccountService($this->createRepository(null, $context));

        $this->expectException(BadCredentialsException::class);
        $accountService->getMemberByLogin('unknown@example.com', 'any-password', $context);
    }

    public function testInactiveMemberCannotLogIn(): void
    {
        $context = $this->createContext();
        $member = $this->createMember($context->getChannelId());
        $member->setActive(false);
        $accountService = $this->createAccountService($this->createRepository($member, $context));

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'contena', $context);
    }

    public function testLoginByIdRejectsInvalidUuid(): void
    {
        $accountService = $this->createAccountService($this->createRepository(null, $this->createContext()));

        $this->expectException(BadCredentialsException::class);
        $accountService->loginById('not-a-uuid', $this->createContext());
    }

    public function testLoginById(): void
    {
        $context = $this->createContext();
        $member = $this->createMember($context->getChannelId());
        $loggedInContext = $this->createContext($member, 'new-token');

        $restorer = $this->createMock(MemberContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restore')
            ->with($member->getId(), $context)
            ->willReturn($loggedInContext);

        $accountService = new AccountService(
            $this->createRepository($member, $context),
            new EventDispatcher(),
            $restorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );

        static::assertSame('new-token', $accountService->loginById($member->getId(), $context));
    }

    public function testLoginByIdNotFound(): void
    {
        $context = $this->createContext();
        $id = Uuid::randomHex();
        $accountService = $this->createAccountService($this->createRepository(null, $context));

        $this->expectExceptionObject(new MemberNotFoundByIdException($id));
        $accountService->loginById($id, $context);
    }

    public function testPasswordTooLongThrowsBadCredentials(): void
    {
        $accountService = $this->createAccountService($this->createRepository(null, $this->createContext()));

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials(
            'foo@bar.de',
            str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1),
            $this->createContext(),
        );
    }

    public function testUnconfirmedDoubleOptInResendsMail(): void
    {
        $context = $this->createContext();
        $member = $this->createMember($context->getChannelId());
        $member->setDoubleOptInRegistration(true);

        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->expects($this->once())
            ->method('resendDoubleOptInMail')
            ->with($member, $context);

        $accountService = new AccountService(
            $this->createRepository($member, $context),
            new EventDispatcher(),
            static::createStub(MemberContextRestorer::class),
            $doubleOptInService,
            new NativeClock(),
        );

        $this->expectException(MemberOptinNotCompletedException::class);
        $accountService->getMemberByLogin('foo@bar.de', 'contenaAdmin', $context);
    }

    /**
     * @return StaticEntityRepository<MemberCollection>
     */
    private function createRepository(?MemberEntity $member, ChannelContext $context): StaticEntityRepository
    {
        $collection = $member === null ? new MemberCollection() : new MemberCollection([$member]);

        return StaticEntityRepository::of(MemberCollection::class, [
            new EntitySearchResult($collection->count(), $collection, null, new Criteria(), $context->getContext()),
        ]);
    }

    /**
     * @param StaticEntityRepository<MemberCollection> $repository
     */
    private function createAccountService(StaticEntityRepository $repository): AccountService
    {
        return new AccountService(
            $repository,
            new EventDispatcher(),
            static::createStub(MemberContextRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
        );
    }

    private function createContext(?MemberEntity $member = null, string $token = 'old-token'): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $group = new MemberGroupEntity();
        $group->setId(Uuid::randomHex());

        return Generator::generateChannelContext(
            token: $token,
            channel: $channel,
            currentMemberGroup: $group,
            member: $member,
        );
    }

    private function createMember(string $channelId): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setGroupId(Uuid::randomHex());
        $member->setChannelId($channelId);
        $member->setLanguageId(Uuid::randomHex());
        $member->setMemberNumber('M-1000');
        $member->setName('Test Member');
        $member->setEmail('foo@bar.de');
        $member->setPassword(TestDefaults::HASHED_PASSWORD);
        $member->setActive(true);
        $member->setDoubleOptInRegistration(false);

        return $member;
    }
}
