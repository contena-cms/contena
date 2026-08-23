<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * Regression test for issue #4116 / NEXT-30478:
 * Changing the SEO URL template under Settings > Content > SEO must regenerate
 * the existing SEO URLs without the administrator having to trigger the indexer
 * manually (which is impossible on SaaS).
 *
 * @internal
 */
class SeoUrlTemplateChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    /**
     * @var EntityRepository<SeoUrlTemplateCollection>
     */
    private EntityRepository $seoUrlTemplateRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->seoUrlTemplateRepository = static::getContainer()->get('seo_url_template.repository');
        $this->context = $this->createTenantContext($this->createTenant());
        $this->seoUrlTemplateRepository->create([[
            'id' => Uuid::randomHex(),
            'channelId' => null,
            'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => CategoryDefinition::ENTITY_NAME,
            'template' => '{{ category.name }}',
        ]], $this->context);
        $this->clearQueue();
    }

    public function testChangingSeoUrlTemplateRegeneratesExistingUrls(): void
    {
        $ids = new IdsCollection();
        $context = $this->context;

        // Arrange: a small navigation tree + channel, which generates the
        // default SEO URLs on creation ("a/", "a/b/").
        $this->categoryRepository->create([
            ['id' => $ids->create('root'), 'name' => 'root', 'active' => true],
            ['id' => $ids->create('a'), 'name' => 'a', 'parentId' => $ids->get('root'), 'active' => true],
            ['id' => $ids->create('b'), 'name' => 'b', 'parentId' => $ids->get('a'), 'active' => true],
        ], $context);

        $this->createChannel($ids->create('channel'), $ids->get('root'));

        // The channel navigation entry point is detected and the baseline
        // SEO URLs are generated asynchronously, so drain the queue first.
        $this->runWorker();

        $urls = $this->getSeoUrls($ids->getList(['a', 'b']), $ids->get('channel'));
        static::assertNotEmpty($urls, 'baseline SEO URLs must exist before the template change');
        $beforePaths = array_keys($urls);

        // Act: change the SEO URL template under Settings > Content > SEO. The
        // subscriber must automatically reindex every category for this route
        // so existing URLs are regenerated without a manual indexer run.
        // The override is bound to the test's own channel: a second row for
        // `channelId = null` would collide with the default template seeded by
        // Migration1595492054SeoUrlTemplateData, which SeoUrlUpdater::loadUrlTemplate()
        // collapses into one unordered key so either row could win.
        $customTemplate = 'custom-prefix/{{ category.name }}';
        $this->seoUrlTemplateRepository->create([
            [
                'id' => $ids->create('template'),
                'channelId' => $ids->get('channel'),
                'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => CategoryDefinition::ENTITY_NAME,
                'template' => $customTemplate,
            ],
        ], $context);

        // The subscriber dispatches the regeneration to the message bus; process
        // it so the new URLs are persisted before asserting.
        $this->runWorker();

        // Assert: new SEO URLs exist reflecting the new template, without the
        // SEO indexer being triggered manually.
        $urls = $this->getSeoUrls($ids->getList(['a', 'b']), $ids->get('channel'));
        $afterPaths = array_keys($urls);

        $regenerated = array_values(array_filter(
            $afterPaths,
            static fn (string $path): bool => str_starts_with($path, 'custom-prefix/')
        ));
        static::assertNotEmpty(
            $regenerated,
            'subscriber must regenerate SEO URLs under the custom prefix after the template change; '
                . 'baseline=' . implode(',', $beforePaths) . ' after=' . implode(',', $afterPaths)
        );
    }

    public function testUpdatingAnExistingSeoUrlTemplateRegeneratesUrls(): void
    {
        $ids = new IdsCollection();
        $context = $this->context;

        $this->categoryRepository->create([
            ['id' => $ids->create('root'), 'name' => 'root', 'active' => true],
            ['id' => $ids->create('a'), 'name' => 'a', 'parentId' => $ids->get('root'), 'active' => true],
        ], $context);

        $this->createChannel($ids->create('channel'), $ids->get('root'));

        $this->runWorker();

        $urls = $this->getSeoUrls($ids->getList(['a']), $ids->get('channel'));
        static::assertNotEmpty($urls, 'baseline SEO URLs must exist before updating the template');

        $templateId = $this->findDefaultTemplateId(NavigationPageSeoUrlRoute::ROUTE_NAME);
        static::assertNotNull($templateId);

        $this->seoUrlTemplateRepository->update([
            [
                'id' => $templateId,
                'template' => 'v2/{{ category.name }}',
            ],
        ], $context);

        $this->runWorker();

        $urls = $this->getSeoUrls($ids->getList(['a']), $ids->get('channel'));
        $regenerated = array_values(array_filter(
            array_keys($urls),
            static fn (string $path): bool => str_starts_with($path, 'v2/')
        ));
        static::assertNotEmpty(
            $regenerated,
            'subscriber must regenerate SEO URLs under the v2/ prefix after the template update'
        );
    }

    public function testUpdatingOnlyCustomFieldsDoesNotTriggerReindex(): void
    {
        $ids = new IdsCollection();
        $context = $this->context;

        $this->categoryRepository->create([
            ['id' => $ids->create('root'), 'name' => 'root', 'active' => true],
            ['id' => $ids->create('a'), 'name' => 'a', 'parentId' => $ids->get('root'), 'active' => true],
        ], $context);

        $this->createChannel($ids->create('channel'), $ids->get('root'));

        $this->runWorker();

        $urlsBefore = $this->getSeoUrls($ids->getList(['a']), $ids->get('channel'));
        static::assertNotEmpty($urlsBefore, 'baseline SEO URLs must exist');

        $templateId = $this->findDefaultTemplateId(NavigationPageSeoUrlRoute::ROUTE_NAME);
        static::assertNotNull($templateId);

        // Update only an unrelated field. The subscriber should skip the
        // expensive reindex because the template did not change.
        $this->seoUrlTemplateRepository->update([
            [
                'id' => $templateId,
                'customFields' => ['unrelated' => 'value'],
            ],
        ], $context);

        // No template field changed, so the subscriber must not dispatch a
        // regeneration message; draining the queue must leave the URLs untouched.
        $this->runWorker();

        $urlsAfter = $this->getSeoUrls($ids->getList(['a']), $ids->get('channel'));
        static::assertSame($urlsBefore, $urlsAfter);
    }

    public function testResubmittingSameTemplateDoesNotTriggerReindex(): void
    {
        $context = $this->context;

        $templateId = $this->findDefaultTemplateId(NavigationPageSeoUrlRoute::ROUTE_NAME);
        static::assertNotNull($templateId);

        $template = 'idempotent/{{ category.name }}';
        $this->seoUrlTemplateRepository->update([
            ['id' => $templateId, 'template' => $template],
        ], $context);

        static::assertSame(1, $this->getDispatchedMessageCount(SeoUrlTemplateIndexingMessage::class));

        // An idempotent write submitting the identical template again must not
        // enqueue another full reindex of the route.
        $this->seoUrlTemplateRepository->update([
            ['id' => $templateId, 'template' => $template],
        ], $context);

        static::assertSame(1, $this->getDispatchedMessageCount(SeoUrlTemplateIndexingMessage::class));
    }

    public function testManuallyModifiedSeoUrlsSurviveTemplateChange(): void
    {
        $ids = new IdsCollection();
        $context = $this->context;

        $this->categoryRepository->create([
            ['id' => $ids->create('root'), 'name' => 'root', 'active' => true],
            ['id' => $ids->create('a'), 'name' => 'a', 'parentId' => $ids->get('root'), 'active' => true],
            ['id' => $ids->create('b'), 'name' => 'b', 'parentId' => $ids->get('a'), 'active' => true],
        ], $context);

        $this->createChannel($ids->create('channel'), $ids->get('root'));

        $this->runWorker();

        // Manually override the SEO URL of category "a", like an administrator does on
        // the category detail page. The override is flagged as modified.
        $canonical = $this->getCanonicalSeoUrl($ids->get('a'), $ids->get('channel'));
        static::assertNotNull($canonical);

        $seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $seoUrlRepository->update([
            [
                'id' => $canonical['id'],
                'seoPathInfo' => 'my-manual-path/',
                'isModified' => true,
            ],
        ], $context);

        // Bound to this test's channel, so the seeded default template cannot
        // win the unordered lookup in SeoUrlUpdater::loadUrlTemplate().
        $this->seoUrlTemplateRepository->create([
            [
                'id' => $ids->create('template'),
                'channelId' => $ids->get('channel'),
                'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'entityName' => CategoryDefinition::ENTITY_NAME,
                'template' => 'v3/{{ category.name }}',
            ],
        ], $context);

        $this->runWorker();

        // The untouched category must follow the new template ...
        $canonicalB = $this->getCanonicalSeoUrl($ids->get('b'), $ids->get('channel'));
        static::assertNotNull($canonicalB);
        static::assertStringStartsWith('v3/', $canonicalB['seoPathInfo']);

        // ... while the manual override must not be replaced by the regeneration.
        $canonicalA = $this->getCanonicalSeoUrl($ids->get('a'), $ids->get('channel'));
        static::assertNotNull($canonicalA);
        static::assertSame('my-manual-path/', $canonicalA['seoPathInfo']);
    }

    /**
     * @return array{id: string, seoPathInfo: string}|null
     */
    private function getCanonicalSeoUrl(string $foreignKey, string $channelId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) as id, seo_path_info as seoPathInfo
             FROM seo_url
             WHERE foreign_key = :foreignKey
               AND route_name = :routeName
               AND language_id = :language
               AND channel_id = :channel
               AND is_canonical = 1',
            [
                'foreignKey' => Uuid::fromHexToBytes($foreignKey),
                'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'channel' => Uuid::fromHexToBytes($channelId),
            ]
        );

        /** @var array{id: string, seoPathInfo: string}|false $row */
        return $row === false ? null : $row;
    }

    private function findDefaultTemplateId(string $routeName): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('channelId', null));

        $first = $this->seoUrlTemplateRepository
            ->searchIds($criteria, $this->context)
            ->firstId();

        return $first;
    }

    private function createChannel(string $id, string $navigationId): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => $this->getValidCountryId(),
            'navigationCategoryId' => $navigationId,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://seo-config-reindex.test',
                ],
            ],
        ];

        $this->channelRepository->create([$data], $this->context);
    }

    /**
     * @param array<string, string> $ids
     *
     * @return array<string, string>
     */
    private function getSeoUrls(array $ids, string $channelId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('seo_path_info', 'path_info');
        $query->from('seo_url');
        $query->andWhere('foreign_key IN (:ids)');
        $query->andWhere('route_name = :routeName');
        $query->andWhere('language_id = :language');
        $query->andWhere('channel_id = :channel');

        $query->setParameter('channel', Uuid::fromHexToBytes($channelId));
        $query->setParameter('ids', Uuid::fromHexToBytesList(array_values($ids)), ArrayParameterType::BINARY);
        $query->setParameter('routeName', NavigationPageSeoUrlRoute::ROUTE_NAME);
        $query->setParameter('language', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM));

        return FetchModeHelper::keyPair($query->executeQuery()->fetchAllAssociative());
    }
}
