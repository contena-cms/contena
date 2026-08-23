<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\BaseChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ContextFactory;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[CoversClass(BaseChannelContextFactory::class)]
class BaseChannelContextFactoryTest extends TestCase
{
    public function testNoContextData(): void
    {
        $channelId = Uuid::randomHex();
        $factory = $this->createFactory(false);

        $this->expectExceptionObject(ChannelException::noContextData($channelId));
        $factory->create($channelId);
    }

    public function testChannelNotFound(): void
    {
        $channelId = Uuid::randomHex();
        $factory = $this->createFactory([
            'channel_default_language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
        ]);

        $this->expectExceptionObject(ChannelException::channelNotFound($channelId));
        $factory->create($channelId);
    }

    public function testMemberGroupNotFound(): void
    {
        $channel = $this->createChannel();
        $factory = $this->createFactory(
            [
                'channel_default_language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            new ChannelCollection([$channel]),
        );

        $this->expectExceptionObject(ChannelException::memberGroupNotFound($channel->getMemberGroupId()));
        $factory->create($channel->getId());
    }

    public function testCountryNotFound(): void
    {
        $channel = $this->createChannel();
        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId($channel->getMemberGroupId());
        $factory = $this->createFactory(
            [
                'channel_default_language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            new ChannelCollection([$channel]),
            new MemberGroupCollection([$memberGroup]),
        );

        $this->expectExceptionObject(ChannelException::countryNotFound($channel->getCountryId()));
        $factory->create($channel->getId());
    }

    public function testCreatesContextWithLanguageAndVersion(): void
    {
        $channel = $this->createChannel();
        $memberGroup = new MemberGroupEntity();
        $memberGroup->setId($channel->getMemberGroupId());
        $country = new CountryEntity();
        $country->setId($channel->getCountryId());
        $language = new PartialEntity();
        $language->assign([
            'id' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'English',
            'locale' => new PartialEntity()->assign(['code' => 'en-GB']),
        ]);
        $factory = $this->createFactory(
            [
                'channel_default_language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            new ChannelCollection([$channel]),
            new MemberGroupCollection([$memberGroup]),
            new CountryCollection([$country]),
            new EntityCollection([$language]),
        );

        $context = $factory->create($channel->getId(), [
            ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            ChannelContextService::VERSION_ID => 'version-id',
        ]);

        static::assertSame($channel->getId(), $context->getChannelId());
        static::assertSame($memberGroup, $context->getCurrentMemberGroup());
        static::assertSame($country, $context->getCountry());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getContext()->getLanguageId());
        static::assertSame('version-id', $context->getContext()->getVersionId());
        static::assertSame('English', $context->getLanguageInfo()->name);
        static::assertSame('en-GB', $context->getLanguageInfo()->localeCode);
    }

    /**
     * @param false|array<string, mixed> $contextData
     * @param EntityCollection<PartialEntity>|null $languages
     */
    private function createFactory(
        false|array $contextData,
        ?ChannelCollection $channels = null,
        ?MemberGroupCollection $memberGroups = null,
        ?CountryCollection $countries = null,
        /** @var EntityCollection<PartialEntity>|null $languages */
        ?EntityCollection $languages = null,
    ): BaseChannelContextFactory {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($contextData);
        $contextFactory = new ContextFactory($connection, new CollectingEventDispatcher());

        /** @var Stub&EntityRepository<EntityCollection<PartialEntity>> $languageRepository */
        $languageRepository = static::createStub(EntityRepository::class);
        if ($languages !== null) {
            $languageRepository->method('search')->willReturnCallback(
                static function (Criteria $criteria, Context $context) use ($languages): EntitySearchResult {
                    return new EntitySearchResult(
                        $languages->count(),
                        $languages,
                        null,
                        $criteria,
                        $context,
                    );
                },
            );
        }

        return new BaseChannelContextFactory(
            StaticEntityRepository::of(ChannelCollection::class, [$channels ?? new ChannelCollection()]),
            StaticEntityRepository::of(MemberGroupCollection::class, [$memberGroups ?? new MemberGroupCollection()]),
            StaticEntityRepository::of(CountryCollection::class, [$countries ?? new CountryCollection()]),
            $contextFactory,
            $languageRepository,
        );
    }

    private function createChannel(): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setMemberGroupId(Uuid::randomHex());
        $channel->setCountryId(Uuid::randomHex());
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        return $channel;
    }
}
