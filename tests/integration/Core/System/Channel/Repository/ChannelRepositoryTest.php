<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    public function testCreatesAndLoadsGenericChannelAssociations(): void
    {
        $channelId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $categoryId = $this->getValidCategoryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        static::assertNotNull($snippetSetId);

        $name = 'Repository test channel';
        $accessKey = AccessKeyHelper::generateAccessKey('channel');
        $typeName = 'Repository test type';
        $domain = 'https://' . Uuid::randomHex() . '.example.org';

        $this->channelRepository->create([[
            'id' => $channelId,
            'name' => $name,
            'type' => [
                'name' => $typeName,
                'manufacturer' => 'Contena',
                'description' => 'Generic Channel type',
                'descriptionLong' => 'Generic Channel type description',
                'iconName' => 'regular-globe',
                'screenshotUrls' => ['https://example.org/channel.png'],
            ],
            'accessKey' => $accessKey,
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => $countryId,
            'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'navigationCategoryId' => $categoryId,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'countries' => [['id' => $countryId]],
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $snippetSetId,
                'url' => $domain,
            ]],
        ]], Context::createDefaultContext());

        $criteria = new Criteria([$channelId]);
        $criteria->addAssociation('type');
        $criteria->addAssociation('languages');
        $criteria->addAssociation('countries');
        $criteria->addAssociation('domains');

        $channel = $this->channelRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($channelId);

        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertSame($name, $channel->getName());
        static::assertSame($accessKey, $channel->getAccessKey());
        static::assertSame($countryId, $channel->getCountryId());
        static::assertSame(TestDefaults::FALLBACK_MEMBER_GROUP, $channel->getMemberGroupId());
        $languages = $channel->getLanguages();
        $countries = $channel->getCountries();
        $domains = $channel->getDomains();
        static::assertNotNull($languages);
        static::assertNotNull($countries);
        static::assertNotNull($domains);
        static::assertCount(1, $languages);
        static::assertCount(1, $countries);
        static::assertCount(1, $domains);

        $type = $channel->getType();
        static::assertInstanceOf(ChannelTypeEntity::class, $type);
        static::assertSame($typeName, $type->getName());
        static::assertSame('regular-globe', $type->getIconName());
        static::assertSame([$domain], [$domains->first()?->getUrl()]);
    }
}
