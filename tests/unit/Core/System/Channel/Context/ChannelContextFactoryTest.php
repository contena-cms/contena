<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\BaseChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\AbstractBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ChannelContextFactory::class)]
class ChannelContextFactoryTest extends TestCase
{
    public function testMemberIsNullIfInactive(): void
    {
        $channel = $this->createChannel();
        $member = $this->createMember($channel, false);
        $baseContext = $this->createBaseContext($channel);

        $factory = new ChannelContextFactory(
            StaticEntityRepository::of(MemberCollection::class, [new MemberCollection([$member])]),
            StaticEntityRepository::of(MemberGroupCollection::class),
            static::createStub(EventDispatcherInterface::class),
            $this->createBaseFactory($channel, $baseContext),
        );

        $context = $factory->create(
            'context-token',
            $channel->getId(),
            [
                'memberId' => $member->getId(),
            ],
        );

        static::assertNull($context->getMember());
        static::assertSame($baseContext->getCurrentMemberGroup(), $context->getCurrentMemberGroup());
    }

    public function testMemberIsSetIfActive(): void
    {
        $channel = $this->createChannel();
        $member = $this->createMember($channel, true);
        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId($member->getGroupId());
        $baseContext = $this->createBaseContext($channel);

        $factory = new ChannelContextFactory(
            StaticEntityRepository::of(MemberCollection::class, [new MemberCollection([$member])]),
            StaticEntityRepository::of(MemberGroupCollection::class, [new MemberGroupCollection([$memberGroup])]),
            static::createStub(EventDispatcherInterface::class),
            $this->createBaseFactory($channel, $baseContext),
        );

        $context = $factory->create(
            'context-token',
            $channel->getId(),
            [
                'memberId' => $member->getId(),
            ],
        );

        static::assertSame($member, $context->getMember());
        static::assertSame($memberGroup, $context->getCurrentMemberGroup());
    }

    private function createBaseFactory(ChannelEntity $channel, BaseChannelContext $baseContext): AbstractBaseChannelContextFactory
    {
        $factory = $this->createMock(AbstractBaseChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($channel->getId(), static::arrayHasKey('memberId'))
            ->willReturn($baseContext);

        return $factory;
    }

    private function createChannel(): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);

        return $channel;
    }

    private function createMember(ChannelEntity $channel, bool $active): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId('member-id');
        $member->setChannelId($channel->getId());
        $member->setGroupId('member-group-id');
        $member->setActive($active);

        return $member;
    }

    private function createBaseContext(ChannelEntity $channel): BaseChannelContext
    {
        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId('fallback-member-group-id');

        $country = new CountryEntity();
        $country->setId('country-id');

        return new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $memberGroup,
            $country,
            new LanguageInfo('English', 'en-GB'),
        );
    }
}
