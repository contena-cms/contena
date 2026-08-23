<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SeoUrlUpdaterTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    // Language codes
    private const DEFAULT = 'zh-CN';
    private const PARENT = 'en-GB';
    private const CHILD = 'en-GB-1';

    private IdsCollection $ids;

    /**
     * @var array<string, mixed>
     */
    private array $frontendChannel;

    /**
     * @var array<string, mixed>
     */
    private array $apiChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();

        // Get language ids
        $this->ids->set(self::DEFAULT, Defaults::LANGUAGE_SYSTEM);
        $this->ids->set(self::PARENT, $this->getEnglishLanguageId());
        $this->ids->create(self::CHILD);

        $channelOverride = [
            // Create child language
            'language' => [
                'id' => $this->ids->get(self::CHILD),
                'name' => self::CHILD,
                'parentId' => $this->ids->get(self::PARENT),
                'active' => true,
                // Create locale for child language
                'locale' => [
                    'id' => $this->ids->create('childLocale'),
                    'code' => self::CHILD,
                    'translations' => [
                        [
                            'languageId' => $this->ids->get(self::DEFAULT),
                            'name' => self::CHILD,
                            'territory' => self::CHILD,
                        ],
                    ],
                ],
                'translationCodeId' => $this->ids->get('childLocale'),
            ],
            'languages' => [['id' => $this->ids->get(self::CHILD)]],
            // Add domain for child language
            'domains' => [
                [
                    'languageId' => $this->ids->get(self::CHILD),
                    'snippetSetId' => $this->getSnippetSetIdForLocale(self::PARENT),
                ],
            ],
        ];

        // Create web channel for child language
        $frontendChannelOverride = $channelOverride;
        $frontendChannelOverride['typeId'] = Defaults::CHANNEL_TYPE_WEB;
        $frontendChannelOverride['domains'][0]['url'] = 'http://localhost/frontend';
        $this->frontendChannel = $this->createChannel($frontendChannelOverride);

        // Create API channel.
        $apiChannelOverride = $channelOverride;
        $apiChannelOverride['typeId'] = Defaults::CHANNEL_TYPE_API;
        $apiChannelOverride['domains'][0]['url'] = 'http://localhost/api';
        $apiChannelOverride['domains'][0]['isExternalFrontend'] = true;
        $this->apiChannel = $this->createChannel($apiChannelOverride);
    }

    /**
     * Checks whether the seo url updater is using the correct language for translations.
     *
     * @param list<string> $translations
     * @param non-empty-string $pathInfo
     */
    #[DataProvider('seoLanguageDataProvider')]
    public function testSeoLanguageInheritance(array $translations, string $pathInfo): void
    {
        static::getContainer()->get(Connection::class)->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'route_name' => TestBlogSeoUrlRoute::ROUTE_NAME,
            'entity_name' => BlogDefinition::ENTITY_NAME,
            'template' => '{{ blog.translated.name }}',
            'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $blogBuilder = new BlogBuilder($this->ids, 'p1')
            ->name(self::DEFAULT);

        foreach ($translations as $translation) {
            $blogBuilder->translation($this->ids->get($translation), 'name', $translation);
        }

        static::getContainer()->get('blog.repository')->create([
            $blogBuilder->build(),
        ], Context::createDefaultContext());

        // Manually trigger the updater, as the automatic updater triggers only for frontend routes
        static::getContainer()->get(SeoUrlUpdater::class)->update(
            TestBlogSeoUrlRoute::ROUTE_NAME,
            [$this->ids->get('p1')]
        );

        // Search for created SEO URL of the web channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('p1')));
        $criteria->addFilter(new EqualsFilter('routeName', TestBlogSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('channelId', $this->frontendChannel['id']));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        // Check if seo url was created
        static::assertNotNull($seoUrl);

        // Check if seo path matches the expected path
        static::assertStringStartsWith($pathInfo, $seoUrl->getSeoPathInfo());

        // Verify URL of the API channel.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', TestBlogSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('channelId', $this->apiChannel['id']));
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        // Check that no seo url was created.
        static::assertNull($seoUrl);
    }

    public function testHeadlessChannelSeoUrlsAreGeneratedForVisibleBlogsOnly(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'channel_id' => Uuid::fromHexToBytes($this->apiChannel['id']),
            'route_name' => BlogChannelApiUrlRoute::ROUTE_NAME,
            'entity_name' => BlogDefinition::ENTITY_NAME,
            'template' => '{{ blog.translated.name }}',
            'is_headless' => 1,
            'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $visible = new BlogBuilder($this->ids, 'visible')
            ->name('visible-blog')
            ->visibility($this->apiChannel['id'])
            ->build();
        $hidden = new BlogBuilder($this->ids, 'hidden')
            ->name('hidden-blog')
            ->build();

        static::getContainer()->get('blog.repository')->create([$visible, $hidden], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            BlogChannelApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('visible'), $this->ids->get('hidden')]
        );

        $seoUrl = $this->findHeadlessBlogSeoUrl($this->ids->get('visible'));
        static::assertNotNull($seoUrl);
        static::assertSame($this->apiChannel['id'], $seoUrl->getChannelId());
        static::assertSame('visible-blog', $seoUrl->getSeoPathInfo());
        static::assertNull($this->findHeadlessBlogSeoUrl($this->ids->get('hidden')));
    }

    public function testHeadlessChannelWithoutExternalFrontendDomainGeneratesNoSeoUrls(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE `channel_domain` SET `is_external_frontend` = 0 WHERE `channel_id` = :id',
            ['id' => Uuid::fromHexToBytes($this->apiChannel['id'])]
        );
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'channel_id' => Uuid::fromHexToBytes($this->apiChannel['id']),
            'route_name' => BlogChannelApiUrlRoute::ROUTE_NAME,
            'entity_name' => BlogDefinition::ENTITY_NAME,
            'template' => '{{ blog.translated.name }}',
            'is_headless' => 1,
            'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $blog = new BlogBuilder($this->ids, 'no-external')
            ->name('no-external-blog')
            ->visibility($this->apiChannel['id'])
            ->build();
        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            BlogChannelApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('no-external')]
        );

        static::assertNull($this->findHeadlessBlogSeoUrl($this->ids->get('no-external')));
    }

    public function testHeadlessChannelInheritsDefaultTemplateWhenChannelTemplateIsNull(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'channel_id' => Uuid::fromHexToBytes($this->apiChannel['id']),
            'route_name' => BlogChannelApiUrlRoute::ROUTE_NAME,
            'entity_name' => BlogDefinition::ENTITY_NAME,
            'template' => null,
            'is_headless' => 1,
            'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $blog = new BlogBuilder($this->ids, 'headless-inherit')
            ->name('inherited-blog')
            ->visibility($this->apiChannel['id'])
            ->build();
        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(
            BlogChannelApiUrlRoute::ROUTE_NAME,
            [$this->ids->get('headless-inherit')]
        );

        $seoUrl = $this->findHeadlessBlogSeoUrl($this->ids->get('headless-inherit'));
        static::assertNotNull($seoUrl);
        static::assertSame('inherited-blog', $seoUrl->getSeoPathInfo());
    }

    /**
     * @return iterable<string, array{translations: list<string>, pathInfo: non-empty-string}>
     */
    public static function seoLanguageDataProvider(): iterable
    {
        yield 'child path info is used when all translations are available' => [
            'translations' => [self::DEFAULT, self::PARENT, self::CHILD],
            'pathInfo' => self::CHILD,
        ];
        yield 'child path info is used when parent translation is missing' => [
            'translations' => [self::DEFAULT, self::CHILD],
            'pathInfo' => self::CHILD,
        ];
        yield 'parent path info is used when child translation is missing' => [
            'translations' => [self::DEFAULT, self::PARENT],
            'pathInfo' => self::PARENT,
        ];
        yield 'default path info is used when parent and child translations are missing' => [
            'translations' => [self::DEFAULT],
            'pathInfo' => self::DEFAULT,
        ];
    }

    private function findHeadlessBlogSeoUrl(string $foreignKey): ?SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $foreignKey));
        $criteria->addFilter(new EqualsFilter('routeName', BlogChannelApiUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('channelId', $this->apiChannel['id']));

        $seoUrl = static::getContainer()->get('seo_url.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        return $seoUrl instanceof SeoUrlEntity ? $seoUrl : null;
    }

    private function getEnglishLanguageId(): string
    {
        $repository = static::getContainer()->get('language.repository');
        $criteria = new Criteria()->addFilter(new EqualsFilter('language.translationCode.code', 'en-GB'));
        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
