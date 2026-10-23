<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class SeoUrlPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendChannelTestHelper;

    private const ANOTHER_ROUTE_NAME = 'another.route';

    private const LANGUAGE_IDS = [
        'en' => '1a2b3c4d5e6f708090a1b2c3d4e5f607',
        'de' => '2b3c4d5e6f708090a1b2c3d4e5f60708',
    ];

    private IdsCollection $ids;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private SeoUrlPersister $seoUrlPersister;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private SeoUrlGenerator $seoUrlGenerator;

    private ChannelEntity $channel;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->seoUrlPersister = static::getContainer()->get(SeoUrlPersister::class);
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->seoUrlGenerator = static::getContainer()->get(SeoUrlGenerator::class);
        $this->channelRepository = static::getContainer()->get('channel.repository');

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `channel`');
        $connection->executeStatement('DELETE FROM `seo_url`');

        $id = $this->createChannel()['id'];
        $channels = $this->channelRepository
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities();

        static::assertInstanceOf(ChannelEntity::class, $channels->first());
        $this->channel = $channels->first();
    }

    public function testUpdateSeoUrlsDefault(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path-2',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);

        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicalUrls);

        $first = $canonicalUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame('fancy-path-2', $first->getSeoPathInfo());

        $obsoletedSeoUrls = $seoUrls->filterByProperty('isCanonical', null);

        static::assertCount(1, $obsoletedSeoUrls);

        $first = $obsoletedSeoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame('fancy-path', $first->getSeoPathInfo());
    }

    public function testGeneratedSeoUrlsWithDisallowedCharactersAreSanitised(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        // Valid percent-escapes (`rawurlencode(slugify(...))` output for
        // non-ASCII slug configs) and query strings are URL-allowed and must
        // survive; only sequences that break the frontend router are
        // filtered (here the `#` and the stray `%`, see #13796).
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'caf%C3%A9/with#frag%/url?q=1',
            ],
        ];

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);

        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $first = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);

        $stored = $first->getSeoPathInfo();
        static::assertDoesNotMatchRegularExpression(
            ValidSeoPathInfo::DISALLOWED_CHARACTERS_PATTERN,
            $stored,
            'Persisted SEO path must not contain router-breaking characters'
        );
        static::assertSame('caf%C3%A9/with-frag-/url?q=1', $stored);
    }

    public function testDuplicatesSameChannel(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $fk1 = Uuid::randomHex();
        $fk2 = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'channelId' => $channelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
            [
                'channelId' => $channelId,
                'foreignKey' => $fk2,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $seoUrlUpdates, $this->channel);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1, $fk2]));

        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $result);
        $first = $result->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($fk2, $first->getForeignKey());
    }

    public function testReturnToPreviousUrl(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $fk1 = Uuid::randomHex();
        $initialSeoUrlUpdates = [
            [
                'channelId' => $channelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($initialSeoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates, $this->channel);

        $intermediateSeoUrlUpdates = [
            [
                'channelId' => $channelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'intermediate',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $intermediateSeoUrlUpdates, $this->channel);
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates, $this->channel);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1]));

        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertCount(2, $result);

        $canonicals = $result->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicals);

        $canonical = $canonicals->first();
        static::assertInstanceOf(SeoUrlEntity::class, $canonical);
        static::assertSame($fk1, $canonical->getForeignKey());
    }

    public function testSameSeoPathDifferentLanguage(): void
    {
        $defaultContext = Context::createDefaultContext();
        $deContext = new Context($defaultContext->getSource(), [], $defaultContext->getCurrencyId(), [$this->getDeDeLanguageId()]);

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($defaultContext, 'r', $fks, $seoUrlUpdates, $this->channel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($deContext, 'r', $fks, $seoUrlUpdates, $this->channel);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));

        $result = $this->seoUrlRepository->search($criteria, $defaultContext)->getEntities();
        static::assertCount(2, $result);
    }

    public function testSameSeoPathInfoDifferentChannels(): void
    {
        $context = Context::createDefaultContext();

        $channelAId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelAId, 'test a');

        $channelBId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelBId, 'test b');

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->channel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'channelId' => $channelAId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->channel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'channelId' => $channelBId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->channel);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, $context)->getEntities();
        static::assertCount(3, $result);
    }

    public function testUpdateDefaultIsModified(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'default',
                'isModified' => false,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-override',
                'isModified' => true,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertSame('fancy-override', $canon->getSeoPathInfo());

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'no-effect',
            ],
        ];

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->channel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertNotSame('no-effect', $canon->getSeoPathInfo());
    }

    public function testUpdateSeoUrlsShouldMarkSeoUrlAsDeleted(): void
    {
        $category = $this->createCategory(false);
        $this->createSeoUrlInDatabase($category->getId(), $this->channel->getId());

        $seoUrls = $this->generateCategorySeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            TestNavigationSeoUrlRoute::ROUTE_NAME,
            [$category->getId()],
            $seoUrls,
            $this->channel
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertTrue($seoUrl->getIsDeleted());
    }

    public function testUpdateSeoUrlsShouldMarkSeoUrlAsNotDeleted(): void
    {
        $isActive = true;
        $category = $this->createCategory($isActive);
        $this->createSeoUrlInDatabase($category->getId(), $this->channel->getId());

        $seoUrls = $this->generateCategorySeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            $seoUrls,
            $this->channel
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testUpdaterDoesNotTouchOtherUrlsFromOtherChannels(): void
    {
        $category = $this->createCategory(true);

        $this->seoUrlRepository->create([
            [
                'foreignKey' => $category->getId(),
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => \sprintf('navigation/%s', $category->getId()),
                'seoPathInfo' => 'FancyCategory',
                'isCanonical' => true,
                'isDeleted' => false,
                'channelId' => $this->channel->getId(),
            ],
        ], Context::createDefaultContext());

        $otherChannelId = $this->createChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://second',
                ],
            ],
        ])['id'];

        $otherChannels = $this->channelRepository
            ->search(new Criteria([$otherChannelId]), Context::createDefaultContext())
            ->getEntities();
        static::assertInstanceOf(ChannelEntity::class, $otherChannels->first());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            [],
            $otherChannels->first()
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testMarkingSeoUrlsAsDeletedLeavesOtherRoutesOfTheSameEntityAlone(): void
    {
        $category = $this->createCategory(true);
        $this->createSeoUrlInDatabase($category->getId(), $this->channel->getId());
        $this->createSeoUrlOfAnotherRoute($category->getId(), isDeleted: false);

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            TestNavigationSeoUrlRoute::ROUTE_NAME,
            [$category->getId()],
            [],
            $this->channel
        );

        static::assertSame(
            [self::ANOTHER_ROUTE_NAME => false, TestNavigationSeoUrlRoute::ROUTE_NAME => true],
            $this->getDeletedFlagsByRouteName($category->getId())
        );
    }

    public function testRestoringSeoUrlsLeavesDeletedSeoUrlsOfOtherRoutesOfTheSameEntityAlone(): void
    {
        $category = $this->createCategory(true);
        $this->createSeoUrlInDatabase($category->getId(), $this->channel->getId());
        $this->createSeoUrlOfAnotherRoute($category->getId(), isDeleted: true);

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            TestNavigationSeoUrlRoute::ROUTE_NAME,
            [$category->getId()],
            [[
                'foreignKey' => $category->getId(),
                'pathInfo' => \sprintf('test/%s', $category->getId()),
                'seoPathInfo' => 'FancyCategory',
                'channelId' => $this->channel->getId(),
                'isCanonical' => true,
                'isModified' => false,
                'isDeleted' => false,
            ]],
            $this->channel
        );

        static::assertSame(
            [self::ANOTHER_ROUTE_NAME => true, TestNavigationSeoUrlRoute::ROUTE_NAME => false],
            $this->getDeletedFlagsByRouteName($category->getId())
        );
    }

    public function testChangedPathInfoIsRetargetedOnEveryRowOfTheEntity(): void
    {
        $category = $this->createCategory(true);
        $this->createSeoUrlInDatabase($category->getId(), $this->channel->getId());
        $this->seoUrlRepository->create([[
            'foreignKey' => $category->getId(),
            'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
            'pathInfo' => \sprintf('test/%s', $category->getId()),
            'channelId' => $this->channel->getId(),
            'seoPathInfo' => 'MerchantCategory',
            'isCanonical' => null,
            'isModified' => true,
            'isDeleted' => false,
        ]], Context::createDefaultContext());

        $retargetedPathInfo = \sprintf('retargeted/%s', $category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            TestNavigationSeoUrlRoute::ROUTE_NAME,
            [$category->getId()],
            [[
                'foreignKey' => $category->getId(),
                'pathInfo' => $retargetedPathInfo,
                'seoPathInfo' => 'FancyCategory',
                'channelId' => $this->channel->getId(),
                'isCanonical' => true,
                'isModified' => false,
                'isDeleted' => false,
            ]],
            $this->channel
        );

        static::assertSame([
            ['seoPathInfo' => 'FancyCategory', 'pathInfo' => $retargetedPathInfo, 'isCanonical' => true, 'isModified' => false],
            ['seoPathInfo' => 'MerchantCategory', 'pathInfo' => $retargetedPathInfo, 'isCanonical' => null, 'isModified' => true],
        ], $this->getSeoUrlsOfCategory($category->getId()));
    }

    public function testUpdateSeoUrlForDifferentChannelsWithSameSeoPathInfo(): void
    {
        $context = Context::createDefaultContext();

        $channels = [
            ['id' => Uuid::randomHex(), 'name' => 'test a'],
            ['id' => Uuid::randomHex(), 'name' => 'test b'],
        ];
        foreach ($channels as $sc) {
            $this->createFrontendChannelContext($sc['id'], $sc['name']);
        }

        $fk = Uuid::randomHex();
        $seoPaths = ['fancy-path', 'fancy-path-2'];

        foreach ($seoPaths as $seoPath) {
            foreach ($channels as $sc) {
                $seoUrlUpdates = [[
                    'foreignKey' => $fk,
                    'channelId' => $sc['id'],
                    'pathInfo' => 'normal/path',
                    'seoPathInfo' => $seoPath,
                    'isCanonical' => true,
                ]];
                $fks = array_column($seoUrlUpdates, 'foreignKey');
                $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->channel);
            }
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        $result = $this->seoUrlRepository->search($criteria, $context)->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $result);
        static::assertCount(4, $result);

        $canonicalUrls = $result->filterByProperty('isCanonical', true);
        static::assertCount(2, $canonicalUrls);

        foreach ($canonicalUrls as $url) {
            static::assertSame('fancy-path-2', $url->getSeoPathInfo());
            static::assertContains($url->getChannelId(), array_column($channels, 'id'));
        }

        $notCanonicalUrls = $result->filterByProperty('isCanonical', null);
        static::assertCount(2, $notCanonicalUrls);

        foreach ($notCanonicalUrls as $url) {
            static::assertSame('fancy-path', $url->getSeoPathInfo());
            static::assertContains($url->getChannelId(), array_column($channels, 'id'));
        }
    }

    public function testMultilingualIsolationCase(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        /** @var EntityRepository<SeoUrlCollection> $seoUrlRepository */
        $seoUrlRepository = static::getContainer()->get('seo_url.repository');

        $context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->createLanguages($context);
        $languageIds = [self::LANGUAGE_IDS['en'], self::LANGUAGE_IDS['de']];

        $blog = (new BlogBuilder($this->ids, 'test'))
            ->name('test a')
            ->price(69)
            ->visibility($this->channel->getId())
            ->build();
        $blogRepository->create([$blog], $context);

        $seoUrlTemplate = '{{ blog.translated.name }}/{{ blog.blogNumber }}';

        foreach ($languageIds as $languageId) {
            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
            $languageContext->setConsiderInheritance(true);

            $blogRepository->update([[
                'id' => $blog['id'],
                'name' => 'test a',
            ]], $languageContext);

            // Generate SEO URLs with correct context and sales channel
            $seoUrls = $this->seoUrlGenerator->generate(
                [$blog['id']],
                $seoUrlTemplate,
                static::getContainer()->get(BlogPageSeoUrlRoute::class),
                $languageContext,
                $this->channel
            );

            $this->seoUrlPersister->updateSeoUrls(
                $languageContext,
                BlogPageSeoUrlRoute::ROUTE_NAME,
                [$blog['id']],
                $seoUrls,
                $this->channel
            );
        }

        $seoUrlsAfterFirstLoop = $this->getAllSeoUrlsForBlog($blog['id'], $seoUrlRepository);
        static::assertCount(\count($languageIds), $seoUrlsAfterFirstLoop, 'SEO URL should be created for each language');

        foreach ($seoUrlsAfterFirstLoop as $seoUrl) {
            static::assertStringContainsString('test-a', $seoUrl['seoPathInfo'], 'SEO URL should contain "test-a" from first loop');
            static::assertTrue($seoUrl['isCanonical'], 'SEO URLs should be canonical after first loop');
        }

        foreach ($languageIds as $languageId) {
            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
            $languageContext->setConsiderInheritance(true);

            $blogRepository->update([[
                'id' => $blog['id'],
                'name' => 'test b',
            ]], $languageContext);

            // Generate SEO URLs with correct context and sales channel
            $seoUrls = $this->seoUrlGenerator->generate(
                [$blog['id']],
                $seoUrlTemplate,
                static::getContainer()->get(BlogPageSeoUrlRoute::class),
                $languageContext,
                $this->channel
            );

            $this->seoUrlPersister->updateSeoUrls(
                $languageContext,
                BlogPageSeoUrlRoute::ROUTE_NAME,
                [$blog['id']],
                $seoUrls,
                $this->channel
            );
        }

        $currentCanonicalUrls = $this->getCurrentCanonicalSeoUrls($blog['id'], $seoUrlRepository);
        static::assertCount(\count($languageIds), $currentCanonicalUrls, 'New canonical SEO URLs should be created for each language');

        foreach ($currentCanonicalUrls as $seoUrl) {
            static::assertStringContainsString('test-b', $seoUrl['seoPathInfo'], 'New SEO URL should contain "test-b" from second loop');
            static::assertTrue($seoUrl['isCanonical'], 'New SEO URLs should be canonical');
        }

        $obsoleteUrls = $this->getObsoleteSeoUrls($blog['id'], $seoUrlRepository);
        static::assertGreaterThanOrEqual(\count($languageIds), \count($obsoleteUrls), 'Old "test a" URLs should be marked as obsolete');

        foreach ($obsoleteUrls as $obsoleteUrl) {
            static::assertStringContainsString('test-a', $obsoleteUrl['seoPathInfo'], 'Obsolete URLs should be the old "test-a" ones');
            static::assertNull($obsoleteUrl['isCanonical'], 'Obsolete URLs should not be canonical');
        }

        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([
            ['id' => self::LANGUAGE_IDS['en']],
            ['id' => self::LANGUAGE_IDS['de']],
        ], $context);
    }

    public function testSeoUrlConflictResolution(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        /** @var EntityRepository<SeoUrlCollection> $seoUrlRepository */
        $seoUrlRepository = static::getContainer()->get('seo_url.repository');

        $context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->createLanguages($context);
        $languageId = self::LANGUAGE_IDS['en'];

        $blog1 = (new BlogBuilder($this->ids, 'blog1'))
            ->name('Awesome Blog')
            ->price(99)
            ->visibility($this->channel->getId())
            ->build();

        $blog2 = (new BlogBuilder($this->ids, 'blog2'))
            ->name('Different Blog')
            ->price(49)
            ->visibility($this->channel->getId())
            ->build();

        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
        $languageContext->setConsiderInheritance(true);

        $blogRepository->create([$blog1, $blog2], $context);

        $blogRepository->update([[
            'id' => $blog1['id'],
            'name' => 'Awesome Blog',
        ]], $languageContext);

        $blogRepository->update([[
            'id' => $blog2['id'],
            'name' => 'Different Blog',
        ]], $languageContext);

        $seoUrlTemplate = '{{ blog.translated.name }}/test';

        $seoUrls = $this->seoUrlGenerator->generate(
            [$blog1['id']],
            $seoUrlTemplate,
            static::getContainer()->get(BlogPageSeoUrlRoute::class),
            $languageContext,
            $this->channel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blog1['id']],
            $seoUrls,
            $this->channel
        );

        $blogRepository->update([[
            'id' => $blog1['id'],
            'name' => 'Awesome Blog Updated', // Creates new canonical, old becomes obsolete
        ]], $languageContext);

        $updatedSeoUrls = $this->seoUrlGenerator->generate(
            [$blog1['id']],
            $seoUrlTemplate,
            static::getContainer()->get(BlogPageSeoUrlRoute::class),
            $languageContext,
            $this->channel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blog1['id']],
            $updatedSeoUrls,
            $this->channel
        );

        $blog1SeoUrls = $this->getAllSeoUrlsForBlog($blog1['id'], $seoUrlRepository);
        static::assertCount(2, $blog1SeoUrls, 'Blog 1 should have 2 SEO URLs (canonical + obsolete)');

        $blog1CanonicalUrl = null;
        $blog1ObsoleteUrl = null;
        foreach ($blog1SeoUrls as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $blog1CanonicalUrl = $seoUrl;
            } else {
                $blog1ObsoleteUrl = $seoUrl;
            }
        }

        static::assertNotNull($blog1CanonicalUrl, 'Blog 1 should have a canonical URL');
        static::assertNotNull($blog1ObsoleteUrl, 'Blog 1 should have an obsolete URL');
        static::assertStringContainsString('Awesome-Blog-Updated', $blog1CanonicalUrl['seoPathInfo']);
        static::assertStringContainsString('Awesome-Blog', $blog1ObsoleteUrl['seoPathInfo']);

        $blogRepository->update([[
            'id' => $blog2['id'],
            'name' => 'Awesome Blog Updated', // Same name = same SEO path
        ]], $languageContext);

        $blog2SeoUrls = $this->seoUrlGenerator->generate(
            [$blog2['id']],
            $seoUrlTemplate,
            static::getContainer()->get(BlogPageSeoUrlRoute::class),
            $languageContext,
            $this->channel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blog2['id']],
            $blog2SeoUrls,
            $this->channel
        );

        $blog1SeoUrlsAfterConflict = $this->getAllSeoUrlsForBlog($blog1['id'], $seoUrlRepository);
        $blog2SeoUrlsAfterConflict = $this->getAllSeoUrlsForBlog($blog2['id'], $seoUrlRepository);

        $blog1NewCanonical = null;
        $blog1ObsoleteUrls = [];
        foreach ($blog1SeoUrlsAfterConflict as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $blog1NewCanonical = $seoUrl;
            } else {
                $blog1ObsoleteUrls[] = $seoUrl;
            }
        }

        static::assertNotNull($blog1NewCanonical, 'Blog 1 should have a canonical URL');
        static::assertStringContainsString('Awesome-Blog', $blog1NewCanonical['seoPathInfo']);
        static::assertCount(0, $blog1ObsoleteUrls);

        $blog2Canonical = null;
        foreach ($blog2SeoUrlsAfterConflict as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $blog2Canonical = $seoUrl;
            }
        }

        static::assertNotNull($blog2Canonical, 'Blog 2 should have a canonical URL');
        static::assertStringContainsString(
            'Awesome-Blog-Updated',
            $blog2Canonical['seoPathInfo'],
            'Blog 2 should have taken over the conflicting path'
        );

        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([
            ['id' => self::LANGUAGE_IDS['en']],
            ['id' => self::LANGUAGE_IDS['de']],
        ], $context);
    }

    public function testRestoreCanonicalDoesNotDuplicateAcrossSameLanguageChannels(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $channelAId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelAId, 'channel-a');
        $channelA = $this->channelRepository->search(new Criteria([$channelAId]), $context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channelA);

        $channelBId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelBId, 'channel-b');
        $channelB = $this->channelRepository->search(new Criteria([$channelBId]), $context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channelB);

        $blog = (new BlogBuilder($this->ids, 'p1'))
            ->name('Old Name')
            ->price(10)
            ->visibility($channelAId)
            ->visibility($channelBId)
            ->build();
        $blogRepository->create([$blog], $context);
        $blogId = $blog['id'];

        $template = '{{ blog.translated.name }}';
        $route = static::getContainer()->get(BlogPageSeoUrlRoute::class);

        $this->seoUrlPersister->updateSeoUrls(
            $context,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blogId],
            $this->seoUrlGenerator->generate([$blogId], $template, $route, $context, $channelA),
            $channelA
        );

        $blogRepository->update([['id' => $blogId, 'name' => 'Shared Name']], $context);

        $this->seoUrlPersister->updateSeoUrls(
            $context,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blogId],
            $this->seoUrlGenerator->generate([$blogId], $template, $route, $context, $channelA),
            $channelA
        );

        $this->seoUrlPersister->updateSeoUrls(
            $context,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$blogId],
            $this->seoUrlGenerator->generate([$blogId], $template, $route, $context, $channelB),
            $channelB
        );

        $sharedCriteria = new Criteria();
        $sharedCriteria->addFilter(new EqualsFilter('foreignKey', $blogId));
        $sharedCriteria->addFilter(new EqualsFilter('channelId', $channelBId));
        $sharedCriteria->addFilter(new EqualsFilter('isCanonical', true));
        $sharedCanonical = $this->seoUrlRepository->search($sharedCriteria, $context)->getEntities()->first();
        static::assertInstanceOf(SeoUrlEntity::class, $sharedCanonical);
        $sharedPath = $sharedCanonical->getSeoPathInfo();

        $otherBlogId = Uuid::randomHex();
        $this->seoUrlPersister->updateSeoUrls(
            $context,
            BlogPageSeoUrlRoute::ROUTE_NAME,
            [$otherBlogId],
            [[
                'foreignKey' => $otherBlogId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => $sharedPath,
            ]],
            $channelA
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $blogId));
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $canonicals = $this->seoUrlRepository->search($criteria, $context)->getEntities();

        static::assertCount(2, $canonicals);

        $canonicalChannelIds = [];
        foreach ($canonicals as $canonical) {
            static::assertSame($sharedPath, $canonical->getSeoPathInfo());
            $canonicalChannelIds[] = $canonical->getChannelId();
        }
        sort($canonicalChannelIds);

        $expectedChannelIds = [$channelAId, $channelBId];
        sort($expectedChannelIds);
        static::assertSame($expectedChannelIds, $canonicalChannelIds);
    }

    private function createLanguages(Context $context): void
    {
        $languages = [[
            'id' => self::LANGUAGE_IDS['en'],
            'name' => 'TestEnglish',
            'locale' => [
                'id' => $this->ids->create('locale-en'),
                'name' => 'TestEnglish',
                'territory' => 'TestEngland',
                'code' => 'en-GB-test',
            ],
            'active' => true,
            'translationCodeId' => $this->ids->get('locale-en'),
        ], [
            'id' => self::LANGUAGE_IDS['de'],
            'name' => 'TestGerman',
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'active' => true,
            'translationCodeId' => $this->ids->get('locale-de'),
        ]];

        $this->getContainer()->get('language.repository')->create($languages, $context);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool|null, isDeleted: bool, languageId: string}>
     */
    private function getAllSeoUrlsForBlog(string $blogId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $blogId));
        $criteria->addFilter(new EqualsFilter('routeName', BlogPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(static fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool|null, isDeleted: bool, languageId: string}>
     */
    private function getCurrentCanonicalSeoUrls(string $blogId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $blogId));
        $criteria->addFilter(new EqualsFilter('routeName', BlogPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsFilter('isDeleted', false));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(static fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool|null, isDeleted: bool, languageId: string}>
     */
    private function getObsoleteSeoUrls(string $blogId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $blogId));
        $criteria->addFilter(new EqualsFilter('routeName', BlogPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('isCanonical', null));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(static fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    private function createCategory(bool $active): CategoryEntity
    {
        $id = Uuid::randomHex();

        $this->categoryRepository->create([[
            'id' => $id,
            'active' => $active,
            'name' => 'FancyCategory',
        ]], Context::createDefaultContext());

        $first = $this->categoryRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(CategoryEntity::class, $first);

        return $first;
    }

    private function getSeoUrlFromDatabase(string $categoryId): ?SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $categoryId));

        return $this->seoUrlRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
    }

    private function createSeoUrlInDatabase(string $categoryId, string $channelId): void
    {
        $this->seoUrlRepository->create([
            [
                'foreignKey' => $categoryId,
                'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                'pathInfo' => \sprintf('test/%s', $categoryId),
                'channelId' => $channelId,
                'seoPathInfo' => 'FancyCategory',
                'isCanonical' => true,
                'isDeleted' => false,
            ],
        ], Context::createDefaultContext());
    }

    private function createSeoUrlOfAnotherRoute(string $categoryId, bool $isDeleted): void
    {
        $this->seoUrlRepository->create([
            [
                'foreignKey' => $categoryId,
                'routeName' => self::ANOTHER_ROUTE_NAME,
                'pathInfo' => \sprintf('another/%s', $categoryId),
                'channelId' => $this->channel->getId(),
                'seoPathInfo' => 'AnotherPath',
                'isCanonical' => true,
                'isDeleted' => $isDeleted,
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, bool>
     */
    private function getDeletedFlagsByRouteName(string $categoryId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $categoryId));

        $flags = [];
        foreach ($this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities() as $seoUrl) {
            $flags[$seoUrl->getRouteName()] = $seoUrl->getIsDeleted();
        }

        ksort($flags);

        return $flags;
    }

    /**
     * @return list<array{seoPathInfo: string, pathInfo: string, isCanonical: bool|null, isModified: bool}>
     */
    private function getSeoUrlsOfCategory(string $categoryId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $categoryId));
        $criteria->addSorting(new FieldSorting('seoPathInfo'));

        return array_values($this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities()->map(
            static fn (SeoUrlEntity $seoUrl): array => [
                'seoPathInfo' => $seoUrl->getSeoPathInfo(),
                'pathInfo' => $seoUrl->getPathInfo(),
                'isCanonical' => $seoUrl->getIsCanonical(),
                'isModified' => $seoUrl->getIsModified(),
            ]
        ));
    }

    private function findRandomChannel(): ChannelEntity
    {
        /** @var ChannelEntity|null $channel */
        $channel = $this->channelRepository
            ->search(
                (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))->setLimit(1),
                Context::createDefaultContext()
            )
            ->getEntities()->first();

        if ($channel === null) {
            static::markTestSkipped('Sales channel with type of frontend is required');
        }

        return $channel;
    }

    /**
     * @return iterable<SeoUrlEntity>
     */
    private function generateCategorySeoUrls(string $categoryId): iterable
    {
        $channel = $this->findRandomChannel();

        $navigation = static::getContainer()->get(TestNavigationSeoUrlRoute::class);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $navigation);

        return $this->seoUrlGenerator->generate(
            [$categoryId],
            'mytemplate',
            $navigation,
            Context::createDefaultContext(),
            $channel
        );
    }
}
