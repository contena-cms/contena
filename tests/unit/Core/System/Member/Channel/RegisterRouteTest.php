<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Member\Channel\RegisterRoute;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RegisterRoute::class)]
class RegisterRouteTest extends TestCase
{
    public function testCustomFields(): void
    {
        $memberRepository = $this->createMemberRepository(
            assertCreate: static function (array $create): void {
                static::assertSame(['mapped' => 1], $create[0]['customFields']);
            },
        );
        $customFieldMapper = new ChannelApiCustomFieldMapper(static::createStub(Connection::class), [
            MemberDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => CustomFieldTypes::INT],
            ],
        ]);

        $register = $this->createRegisterRoute(
            memberRepository: $memberRepository,
            customFieldMapper: $customFieldMapper,
        );

        $register->register(
            new RequestDataBag($this->createRegistrationData([
                'customFields' => [
                    'test' => '1',
                    'mapped' => '1',
                ],
            ])),
            Generator::generateChannelContext(),
            false,
        );
    }

    public function testRedirectParameters(): void
    {
        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->method('mapMemberDoubleOptInData')->willReturnArgument(0);
        $doubleOptInService
            ->expects($this->once())
            ->method('sendDoubleOptInMail')
            ->with(
                static::isInstanceOf(MemberEntity::class),
                static::isInstanceOf(ChannelContext::class),
                'http://localhost:8000',
                'frontend.blog.detail',
                '{"blogId":"018b906b869273fea7926f161dd23911"}',
            );

        $register = $this->createRegisterRoute(
            memberRepository: $this->createMemberRepository(doubleOptInRegistration: true),
            doubleOptInService: $doubleOptInService,
        );

        $register->register(
            new RequestDataBag($this->createRegistrationData([
                'frontendUrl' => 'http://localhost:8000',
                'redirectTo' => 'frontend.blog.detail',
                'redirectParameters' => '{"blogId":"018b906b869273fea7926f161dd23911"}',
            ])),
            Generator::generateChannelContext(),
            false,
        );
    }

    public function testInvalidRedirectParameters(): void
    {
        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->method('mapMemberDoubleOptInData')->willReturnArgument(0);
        $doubleOptInService
            ->expects($this->once())
            ->method('sendDoubleOptInMail')
            ->with(
                static::isInstanceOf(MemberEntity::class),
                static::isInstanceOf(ChannelContext::class),
                'http://localhost:8000',
                'frontend.blog.detail',
                'thisisnotajson',
            );

        $register = $this->createRegisterRoute(
            memberRepository: $this->createMemberRepository(doubleOptInRegistration: true),
            doubleOptInService: $doubleOptInService,
        );

        $register->register(
            new RequestDataBag($this->createRegistrationData([
                'frontendUrl' => 'http://localhost:8000',
                'redirectTo' => 'frontend.blog.detail',
                'redirectParameters' => 'thisisnotajson',
            ])),
            Generator::generateChannelContext(),
            false,
        );
    }

    #[TestDox('Accepts member names with the maximum allowed length of 255 characters')]
    public function testRegisterAcceptsMaximumNameLength(): void
    {
        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'name' => str_repeat('M', MemberDefinition::MAX_LENGTH_NAME),
            ])),
            Generator::generateChannelContext(),
            false,
        );
    }

    #[TestDox('Rejects member names exceeding the maximum allowed length of 255 characters')]
    public function testRegisterRejectsExcessiveNameLength(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value is too long. It should have 255 characters or less.',
                null,
                [],
                'root',
                'name',
                str_repeat('T', MemberDefinition::MAX_LENGTH_NAME + 1),
            ),
        ]);

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        static::expectException(ConstraintViolationException::class);
        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'name' => str_repeat('T', MemberDefinition::MAX_LENGTH_NAME + 1),
            ])),
            Generator::generateChannelContext(),
            false,
        );
    }

    /**
     * @param (\Closure(list<array<string, mixed>>): void)|null $assertCreate
     *
     * @return EntityRepository<MemberCollection>&MockObject
     */
    private function createMemberRepository(
        bool $doubleOptInRegistration = false,
        ?\Closure $assertCreate = null,
    ): EntityRepository&MockObject {
        $member = new MemberEntity();
        $member->setId('member-1');
        $member->setDoubleOptInRegistration($doubleOptInRegistration);
        $member->setEmail('test@example.com');

        $context = Context::createDefaultContext();
        $result = new EntitySearchResult(
            1,
            new MemberCollection([$member]),
            null,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('getDefinition')->willReturn(new MemberDefinition());
        $repository->method('search')->willReturn($result);
        $createCallback = static function (array $create, Context $writeContext) use ($assertCreate): EntityWrittenContainerEvent {
            $assertCreate?->__invoke($create);

            return new EntityWrittenContainerEvent($writeContext, new NestedEventCollection(), []);
        };

        if ($assertCreate !== null) {
            $repository->expects($this->once())->method('create')->willReturnCallback($createCallback);
        } else {
            $repository->method('create')->willReturnCallback($createCallback);
        }

        return $repository;
    }

    /**
     * @param EntityRepository<MemberCollection>|null $memberRepository
     */
    private function createRegisterRoute(
        ?DataValidator $dataValidator = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ChannelApiCustomFieldMapper $customFieldMapper = null,
        ?EntityRepository $memberRepository = null,
        ?DoubleOptInService $doubleOptInService = null,
    ): RegisterRoute {
        $dataValidator ??= static::createStub(DataValidator::class);
        $eventDispatcher ??= new EventDispatcher();
        $customFieldMapper ??= static::createStub(ChannelApiCustomFieldMapper::class);
        $memberRepository ??= $this->createMemberRepository();
        if ($doubleOptInService === null) {
            $doubleOptInService = static::createStub(DoubleOptInService::class);
            $doubleOptInService->method('mapMemberDoubleOptInData')->willReturnArgument(0);
        }

        $validationFactory = static::createStub(DataValidationFactoryInterface::class);
        $validationFactory->method('create')->willReturn(new DataValidationDefinition('member.create'));

        $contextPersister = static::createStub(ChannelContextPersister::class);
        $contextPersister->method('replace')->willReturn('new-token');

        $channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $channelContextService->method('get')->willReturn(Generator::generateChannelContext(token: 'new-token'));

        $numberRangeValueGenerator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $numberRangeValueGenerator->method('getValue')->willReturn('10001');

        return new RegisterRoute(
            $eventDispatcher,
            $numberRangeValueGenerator,
            $dataValidator,
            $validationFactory,
            new StaticSystemConfigService([
                TestDefaults::CHANNEL => [
                    'core.loginRegistration.passwordMinLength' => '8',
                ],
            ]),
            $memberRepository,
            $contextPersister,
            $channelContextService,
            $customFieldMapper,
            $validationFactory,
            $doubleOptInService,
            new NativeClock(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'name' => 'John Doe',
            'phoneNumber' => '123456789',
            'password' => 'contenaAdmin',
        ], $overrides);
    }
}
