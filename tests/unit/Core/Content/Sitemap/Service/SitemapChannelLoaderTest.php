<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Content\Sitemap\Service\SitemapChannelLoader;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SitemapChannelLoader::class)]
class SitemapChannelLoaderTest extends TestCase
{
    public function testLoadChannelsBuildsHeadlessAwareCriteriaAndDispatchesEvent(): void
    {
        $repository = new StaticEntityRepository([new ChannelCollection([])]);

        $capturedCriteria = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SitemapChannelCriteriaEvent::class, static function (SitemapChannelCriteriaEvent $event) use (&$capturedCriteria): void {
            $capturedCriteria = $event->getCriteria();
        });

        $loader = new SitemapChannelLoader($repository, $dispatcher);
        $loader->loadChannels(Context::createDefaultContext());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);

        $filters = $capturedCriteria->getFilters();
        static::assertCount(2, $filters);
        static::assertInstanceOf(NotEqualsFilter::class, $filters[0]);

        $typeFilter = $filters[1];
        static::assertInstanceOf(OrFilter::class, $typeFilter);

        [$frontendFilter, $headlessFilter] = $typeFilter->getQueries();
        static::assertInstanceOf(EqualsFilter::class, $frontendFilter);
        static::assertSame(Defaults::CHANNEL_TYPE_FRONTEND, $frontendFilter->getValue());

        static::assertInstanceOf(AndFilter::class, $headlessFilter);
        [$apiTypeFilter, $externalDomainFilter] = $headlessFilter->getQueries();
        static::assertInstanceOf(EqualsFilter::class, $apiTypeFilter);
        static::assertSame(Defaults::CHANNEL_TYPE_API, $apiTypeFilter->getValue());
        static::assertInstanceOf(EqualsFilter::class, $externalDomainFilter);
        static::assertSame('domains.isExternalFrontend', $externalDomainFilter->getField());
        static::assertTrue($externalDomainFilter->getValue());
    }

    /**
     * @return \Generator<string, array{string, list<array{string, bool}>, list<string>}>
     */
    public static function languageIdCases(): \Generator
    {
        yield 'frontend channels use every domain language once' => [
            Defaults::CHANNEL_TYPE_FRONTEND,
            [['language-a', false], ['language-a', false], ['language-b', false]],
            ['language-a', 'language-b'],
        ];

        yield 'headless channels use only external frontend domain languages' => [
            Defaults::CHANNEL_TYPE_API,
            [['language-a', true], ['language-b', false]],
            ['language-a'],
        ];

        yield 'headless channels without external frontend domain have no languages' => [
            Defaults::CHANNEL_TYPE_API,
            [['language-a', false]],
            [],
        ];
    }

    /**
     * @param list<array{string, bool}> $domainSpecs
     * @param list<string> $expectedLanguageIds
     */
    #[DataProvider('languageIdCases')]
    public function testGetLanguageIds(string $typeId, array $domainSpecs, array $expectedLanguageIds): void
    {
        $domains = new ChannelDomainCollection();
        foreach ($domainSpecs as [$languageId, $isExternalFrontend]) {
            $domain = new ChannelDomainEntity();
            $domain->setId(Uuid::randomHex());
            $domain->setLanguageId($languageId);
            $domain->setIsExternalFrontend($isExternalFrontend);
            $domains->add($domain);
        }

        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId($typeId);
        $channel->setDomains($domains);

        $loader = new SitemapChannelLoader(
            new StaticEntityRepository([new ChannelCollection([])]),
            new EventDispatcher()
        );

        static::assertSame($expectedLanguageIds, $loader->getLanguageIds($channel));
    }

    public function testGetLanguageIdsWithoutLoadedDomains(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId(Defaults::CHANNEL_TYPE_FRONTEND);

        $loader = new SitemapChannelLoader(
            new StaticEntityRepository([new ChannelCollection([])]),
            new EventDispatcher()
        );

        static::assertSame([], $loader->getLanguageIds($channel));
    }
}
