<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\Channel\ResetPasswordRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ResetPasswordRoute::class)]
class ResetPasswordRouteTest extends TestCase
{
    public function testResetsAllRateLimitersOnPasswordReset(): void
    {
        $email = 'Member@Example.com';
        $ip = '10.0.0.1';
        $hash = 'valid-hash';
        $memberId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;
        $tenantId = 'tenant-a';
        $context = Generator::generateChannelContext(Context::createTenantContext($tenantId));

        $member = new MemberEntity();
        $member->setId($memberId);
        $member->setEmail($email);
        $member->setDoubleOptInRegistration(false);

        $recovery = new MemberRecoveryEntity();
        $recovery->setId($recoveryId);
        $recovery->setMember($member);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $memberRecoveryRepository = static::createStub(EntityRepository::class);
        $memberRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new MemberRecoveryCollection([$recovery]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));

        $memberRepository = static::createStub(EntityRepository::class);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key, Context $context) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key, $context->getTenantId()];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key, Context $context) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key, $context->getTenantId()];
            });

        $mainRequest = new Request(server: ['REMOTE_ADDR' => $ip]);
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $route = new ResetPasswordRoute(
            $memberRepository,
            $memberRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $requestStack,
            $rateLimiter,
            $this->createPasswordValidationFactory(),
            new NativeClock(),
        );

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertSame([
            [RateLimiter::LOGIN_ROUTE, $expectedCombinedKey, $tenantId],
            [RateLimiter::RESET_PASSWORD, $expectedCombinedKey, $tenantId],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey, $tenantId],
            [RateLimiter::LOGIN_CLIENT, $ip, $tenantId],
        ], $resetIfConfiguredCalls);
    }

    public function testConfirmsUnconfirmedDoubleOptInMemberOnPasswordReset(): void
    {
        $now = new \DateTimeImmutable('2026-05-30 12:00:00');

        $member = $this->createMember();
        $member->setDoubleOptInRegistration(true);

        $memberUpdate = $this->resetPasswordAndReturnMemberUpdate($member, new MockClock($now));

        static::assertArrayHasKey('doubleOptInConfirmDate', $memberUpdate);
        static::assertEquals($now, $memberUpdate['doubleOptInConfirmDate']);
    }

    public function testDoesNotConfirmMemberWithoutDoubleOptInRegistrationOnPasswordReset(): void
    {
        $member = $this->createMember();
        $member->setDoubleOptInRegistration(false);

        $memberUpdate = $this->resetPasswordAndReturnMemberUpdate($member);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $memberUpdate);
    }

    public function testDoesNotOverwriteExistingDoubleOptInConfirmationOnPasswordReset(): void
    {
        $member = $this->createMember();
        $member->setDoubleOptInRegistration(true);
        $member->setDoubleOptInConfirmDate(new \DateTimeImmutable('2026-05-29 12:00:00'));

        $memberUpdate = $this->resetPasswordAndReturnMemberUpdate($member);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $memberUpdate);
    }

    private function createMember(): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setEmail('member@example.com');

        return $member;
    }

    /**
     * @return array<string, mixed>
     */
    private function resetPasswordAndReturnMemberUpdate(MemberEntity $member, ?MockClock $clock = null): array
    {
        $hash = 'valid-hash';
        $recovery = new MemberRecoveryEntity();
        $recovery->setId(Uuid::randomHex());
        $recovery->setHash($hash);
        $recovery->setMember($member);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $memberRecoveryRepository = static::createStub(EntityRepository::class);
        $memberRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new MemberRecoveryCollection([$recovery]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));
        $memberRecoveryRepository->method('delete')
            ->willReturn(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));

        $memberUpdate = null;
        $memberRepository = $this->createMock(EntityRepository::class);
        $memberRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $updates) use (&$memberUpdate): EntityWrittenContainerEvent {
                $memberUpdate = $updates[0];

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
            });

        $route = new ResetPasswordRoute(
            $memberRepository,
            $memberRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new RequestStack(),
            static::createStub(RateLimiter::class),
            $this->createPasswordValidationFactory(),
            $clock ?? new MockClock(),
        );

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            Generator::generateChannelContext(),
        );

        static::assertIsArray($memberUpdate);

        return $memberUpdate;
    }

    private function createPasswordValidationFactory(): DataValidationFactoryInterface
    {
        $passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        return $passwordValidationFactory;
    }
}
