<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SendPasswordRecoveryMailRoute::class)]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    /**
     * @var EntityRepository<MemberCollection>&Stub
     */
    protected EntityRepository&Stub $memberRepository;

    /**
     * @var EntityRepository<MemberRecoveryCollection>&Stub
     */
    protected EntityRepository&Stub $memberRecoveryRepository;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    protected DataValidator&Stub $validator;

    protected SystemConfigService&Stub $systemConfigService;

    protected RequestStack&Stub $requestStack;

    protected RateLimiter&Stub $rateLimiter;

    protected ChannelContext $context;

    protected function setUp(): void
    {
        $this->memberRepository = static::createStub(EntityRepository::class);
        $this->memberRecoveryRepository = static::createStub(EntityRepository::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->requestStack = static::createStub(RequestStack::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->context = Generator::generateChannelContext();
    }

    public function testSendRecoveryMail(): void
    {
        $member = new MemberEntity();
        $member->setId('foo');

        $memberRepository = $this->createMock(EntityRepository::class);
        $memberRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new MemberCollection([$member]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));

        $memberRecoveryRepository = $this->createMock(EntityRepository::class);
        $memberRecoveryRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static function (array $recoveryData): bool {
                    static::assertCount(1, $recoveryData);

                    $updateData = $recoveryData[0];
                    static::assertArrayHasKey('memberId', $updateData);
                    static::assertArrayHasKey('hash', $updateData);
                    static::assertSame('foo', $updateData['memberId']);
                    static::assertSame(32, \strlen($updateData['hash']));

                    return true;
                }),
                $this->context->getContext(),
            );

        $memberRecovery = new MemberRecoveryEntity();
        $memberRecovery->setId('member-recovery-id');
        $memberRecovery->setUniqueIdentifier('member-recovery-id');
        $memberRecovery->setMemberId($member->getId());
        $memberRecovery->setHash('super-secret-hash');
        $memberRecovery->setMember($member);

        $memberRecoveryRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new MemberRecoveryCollection([$memberRecovery]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));

        $mailRoute = new SendPasswordRecoveryMailRoute(
            $memberRepository,
            $memberRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
        );

        $this->context->getChannel()->setTranslated(['name' => 'FooBar']);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $data = new RequestDataBag();
        $data->set('email', 'test@test.dev');
        $data->set('frontendUrl', 'https://test.example.dev');

        $mailRoute->sendRecoveryMail($data, $this->context);
    }

    public function testNoMemberFound(): void
    {
        $tenantId = 'tenant-a';
        $context = Generator::generateChannelContext(Context::createTenantContext($tenantId));
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::RESET_PASSWORD, 'foo@foo-10.0.0.1', $context->getContext());

        $mailRoute = new SendPasswordRecoveryMailRoute(
            $this->memberRepository,
            $this->memberRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $requestStack,
            $rateLimiter,
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }
}
