<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Member\Channel\RegisterConfirmRoute;
use Contena\Core\System\Member\Exception\MemberAlreadyConfirmedException;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RegisterConfirmRoute::class)]
class RegisterConfirmRouteTest extends TestCase
{
    protected ChannelContext $context;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    /**
     * @var EntityRepository<MemberCollection>&MockObject
     */
    protected EntityRepository&MockObject $memberRepository;

    protected DataValidator&Stub $validator;

    protected ChannelContextPersister&Stub $channelContextPersister;

    protected ChannelContextServiceInterface&Stub $channelContextService;

    protected RegisterConfirmRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Generator::generateChannelContext(token: 'old-token');
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->memberRepository = $this->createMock(EntityRepository::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->channelContextPersister = static::createStub(ChannelContextPersister::class);
        $this->channelContextPersister->method('replace')->willReturn('new-token');

        $this->channelContextService = static::createStub(ChannelContextServiceInterface::class);

        $this->route = $this->createRoute();
    }

    public function testConfirmMember(): void
    {
        $member = $this->mockMember();
        $newChannelContext = Generator::generateChannelContext(token: 'new-token', member: $member);
        $this->channelContextService->method('get')->willReturn($newChannelContext);

        $this->memberRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($this->createSearchResult($member));

        $confirmResult = $this->route->confirm($this->mockRequestDataBag(), $this->context);

        static::assertTrue($confirmResult->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('new-token', $confirmResult->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testConfirmMemberNotDoubleOptIn(): void
    {
        $member = $this->mockMember();
        $member->setDoubleOptInRegistration(false);

        $this->memberRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($member));

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(static function (array $data, DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();
                static::assertArrayHasKey('doubleOptInRegistration', $properties);
                static::assertContainsOnlyInstancesOf(IsTrue::class, $properties['doubleOptInRegistration']);
                static::assertFalse($data['doubleOptInRegistration']);

                throw new ConstraintViolationException(new ConstraintViolationList(), $data);
            });

        $this->expectException(ConstraintViolationException::class);
        $this->createRoute($validator)->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmActivatedMember(): void
    {
        $member = $this->mockMember();
        $member->setActive(true);
        $member->setDoubleOptInConfirmDate(new \DateTime());

        $this->memberRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($member));

        $this->expectException(MemberAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmConfirmedMember(): void
    {
        $member = $this->mockMember();
        $member->setDoubleOptInConfirmDate(new \DateTime());

        $this->memberRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($member));

        $this->expectException(MemberAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    protected function mockMember(): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId('member-1');
        $member->setActive(false);
        $member->setEmail('test@test.test');
        $member->setHash('hash');
        $member->setDoubleOptInRegistration(true);
        $member->setDoubleOptInEmailSentDate(new \DateTime());

        return $member;
    }

    protected function mockRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag([
            'hash' => 'hash',
            'em' => Hasher::hash('test@test.test', 'sha1'),
        ]);
    }

    private function createRoute(?DataValidator $validator = null): RegisterConfirmRoute
    {
        return new RegisterConfirmRoute(
            $this->memberRepository,
            $this->eventDispatcher,
            $validator ?? $this->validator,
            $this->channelContextPersister,
            $this->channelContextService,
            new NativeClock(),
        );
    }

    /**
     * @return EntitySearchResult<MemberCollection>
     */
    private function createSearchResult(MemberEntity $member): EntitySearchResult
    {
        return new EntitySearchResult(
            1,
            new MemberCollection([$member]),
            null,
            new Criteria(),
            $this->context->getContext(),
        );
    }
}
