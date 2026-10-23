<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Extension\SendPasswordRecoveryMailRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SendPasswordRecoveryMailRoute::class)]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    /**
     * @var EntityRepository<UserCollection>&Stub
     */
    protected EntityRepository&Stub $userRepository;

    /**
     * @var EntityRepository<UserRecoveryCollection>&Stub
     */
    protected EntityRepository&Stub $userRecoveryRepository;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    protected DataValidator&Stub $validator;

    protected SystemConfigService&Stub $systemConfigService;

    protected RequestStack&Stub $requestStack;

    protected RateLimiter&Stub $rateLimiter;

    protected ChannelContext $context;

    protected function setUp(): void
    {
        $this->userRepository = static::createStub(EntityRepository::class);
        $this->userRecoveryRepository = static::createStub(EntityRepository::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->requestStack = static::createStub(RequestStack::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->context = Generator::generateChannelContext();
    }

    public function testSendRecoveryMail(): void
    {
        $user = new UserEntity();
        $user->setId('foo');

        $userCollection = new UserCollection([$user]);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user',
                    1,
                    $userCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $userRecoveryRepository = $this->createMock(EntityRepository::class);
        $userRecoveryRepository
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static function (array $recoveryData): bool {
                    static::assertCount(1, $recoveryData);

                    $updateData = $recoveryData[0];

                    static::assertArrayHasKey('userId', $updateData);
                    static::assertArrayHasKey('hash', $updateData);

                    static::assertSame('foo', $updateData['userId']);
                    static::assertSame(32, \strlen($updateData['hash']));

                    return true;
                }),
                $this->context->getContext()
            );

        $userRecovery = new UserRecoveryEntity();
        $userRecovery->setId('user-recovery-id');
        $userRecovery->setUniqueIdentifier('user-recovery-id');
        $userRecovery->setUserId($user->getId());
        $userRecovery->setHash('super-secret-hash');
        $userRecovery->setUser($user);

        $userRecoveryCollection = new UserRecoveryCollection([$userRecovery]);

        $userRecoveryRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user_recovery',
                    1,
                    $userRecoveryCollection,
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $MailRoute = new SendPasswordRecoveryMailRoute(
            $userRepository,
            $userRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->context->getChannel()->setTranslated(['name' => 'FooBar']);

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $data = new RequestDataBag();
        $data->set('email', 'test@test.dev');
        $data->set('frontendUrl', 'https://test.example.dev');

        $MailRoute->sendRecoveryMail($data, $this->context);
    }

    public function testNoUserFound(): void
    {
        $mailRoute = new SendPasswordRecoveryMailRoute(
            $this->userRepository,
            $this->userRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag();
        $data->set('email', 'foo@foo');

        $response = $mailRoute->sendRecoveryMail($data, $this->context)->getObject()->getVars();

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('send-password-recovery-mail-route.send-recovery-mail.pre', function (SendPasswordRecoveryMailRouteExtension $extension) use ($data, $response): void {
            static::assertSame(['data' => $data, 'context' => $this->context, 'validateFrontendUrl' => true], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SendPasswordRecoveryMailRoute(
            $this->userRepository,
            $this->userRecoveryRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->systemConfigService,
            $this->requestStack,
            $this->rateLimiter,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->sendRecoveryMail($data, $this->context, true));
    }
}
