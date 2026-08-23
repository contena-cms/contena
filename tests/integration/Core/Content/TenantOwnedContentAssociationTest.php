<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class TenantOwnedContentAssociationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Content association tenant A')->id;
        $this->tenantB = $this->createTenant('Content association tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createContent($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach (['category' => 'category', 'landing_page' => 'landingPage', 'blog' => 'blog', 'blog_visibility' => 'visibility', 'blog_main_category' => 'mainCategory'] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            foreach ([
                'category_tag' => ['categoryId', 'category'],
                'landing_page_tag' => ['landingPageId', 'landingPage'],
                'landing_page_channel' => ['landingPageId', 'landingPage'],
                'blog_category' => ['blogId', 'blog'],
                'blog_category_tree' => ['blogId', 'blog'],
                'blog_tag' => ['blogId', 'blog'],
            ] as $entityName => [$property, $idKey]) {
                $this->assertMappingCount($entityName, $property, array_column($ids, $idKey), $expectedCounts[$scope], $context, $scope);
            }
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            foreach ([
                ['category', 'id', 'category'],
                ['landing_page', 'id', 'landingPage'],
                ['blog', 'id', 'blog'],
                ['category_tag', 'category_id', 'category'],
                ['landing_page_tag', 'landing_page_id', 'landingPage'],
                ['landing_page_channel', 'landing_page_id', 'landingPage'],
                ['blog_category', 'blog_id', 'blog'],
                ['blog_category_tree', 'blog_id', 'blog'],
                ['blog_tag', 'blog_id', 'blog'],
                ['blog_visibility', 'id', 'visibility'],
                ['blog_main_category', 'id', 'mainCategory'],
            ] as [$table, $idColumn, $idKey]) {
                $this->assertStoredTenant($table, $idColumn, $scopeIds[$idKey], $expectedTenants[$scope]);
            }
        }

        $this->repository('blog_visibility')->update([[
            'id' => $ids['tenant-a']['visibility'],
            'visibility' => BlogVisibilityDefinition::VISIBILITY_SEARCH,
        ]], $this->contexts['tenant-a']);
        $this->repository('blog_main_category')->update([[
            'id' => $ids['tenant-a']['mainCategory'],
            'categoryId' => $ids['tenant-a']['category'],
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(
                fn () => $this->repository('blog_visibility')->update([[
                    'id' => $ids['tenant-a']['visibility'],
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_LINK,
                ]], $this->contexts[$scope]),
                'Expected blog_visibility write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('blog_main_category')->delete([[
                    'id' => $ids['tenant-a']['mainCategory'],
                ]], $this->contexts[$scope]),
                'Expected blog_main_category write protection for ' . $scope,
            );

            foreach ($this->mappingDeletePayloads($ids['tenant-a']) as $entityName => $payload) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->delete([$payload], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }
        }

        $this->assertWriteRejected(
            fn () => $this->repository('blog_tag')->create([[
                'blogId' => $ids['tenant-a']['blog'],
                'tagId' => $ids['tenant-b']['tag'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant blog tag referencing another tenant tag to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('landing_page_channel')->create([[
                'landingPageId' => $ids['tenant-a']['landingPage'],
                'channelId' => $ids['platform']['channel'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant landing page referencing a platform channel to be rejected',
        );
    }

    /**
     * @return array{category: string, landingPage: string, blog: string, channel: string, tag: string, visibility: string, mainCategory: string}
     */
    private function createContent(string $scope, Context $context): array
    {
        $default = $this->repository('channel')->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $groupId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $visibilityId = Uuid::randomHex();
        $mainCategoryId = Uuid::randomHex();

        $this->repository('member_group')->create([[
            'id' => $groupId,
            'name' => 'Content association group ' . $scope,
        ]], $context);
        $this->repository('category')->create([[
            'id' => $categoryId,
            'name' => 'Content association category ' . $scope,
        ]], $context);
        $this->repository('channel')->create([[
            'id' => $channelId,
            'name' => 'Content association channel ' . $scope,
            'accessKey' => 'content-association-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $groupId,
            'navigationCategoryId' => $categoryId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);
        $this->repository('tag')->create([[
            'id' => $tagId,
            'name' => 'Content association tag ' . $scope,
        ]], $context);
        $this->repository('landing_page')->create([[
            'id' => $landingPageId,
            'name' => 'Content association landing page ' . $scope,
            'url' => 'content-association-' . $scope,
            'channels' => [['id' => $channelId]],
        ]], $context);
        $this->repository('blog')->create([[
            'id' => $blogId,
            'type' => BlogDefinition::TYPE_POST,
            'name' => 'Content association blog ' . $scope,
        ]], $context);

        $this->repository('category_tag')->create([[
            'categoryId' => $categoryId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('landing_page_tag')->create([[
            'landingPageId' => $landingPageId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('blog_category')->create([[
            'blogId' => $blogId,
            'categoryId' => $categoryId,
        ]], $context);
        $this->repository('blog_tag')->create([[
            'blogId' => $blogId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('blog_visibility')->create([[
            'id' => $visibilityId,
            'blogId' => $blogId,
            'channelId' => $channelId,
            'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
        ]], $context);
        $this->repository('blog_main_category')->create([[
            'id' => $mainCategoryId,
            'blogId' => $blogId,
            'categoryId' => $categoryId,
            'channelId' => $channelId,
        ]], $context);

        return [
            'category' => $categoryId,
            'landingPage' => $landingPageId,
            'blog' => $blogId,
            'channel' => $channelId,
            'tag' => $tagId,
            'visibility' => $visibilityId,
            'mainCategory' => $mainCategoryId,
        ];
    }

    /**
     * @param array{category: string, landingPage: string, blog: string, channel: string, tag: string, visibility: string, mainCategory: string} $ids
     *
     * @return array<string, array<string, string>>
     */
    private function mappingDeletePayloads(array $ids): array
    {
        return [
            'category_tag' => ['categoryId' => $ids['category'], 'categoryVersionId' => Defaults::LIVE_VERSION, 'tagId' => $ids['tag']],
            'landing_page_tag' => ['landingPageId' => $ids['landingPage'], 'landingPageVersionId' => Defaults::LIVE_VERSION, 'tagId' => $ids['tag']],
            'landing_page_channel' => ['landingPageId' => $ids['landingPage'], 'landingPageVersionId' => Defaults::LIVE_VERSION, 'channelId' => $ids['channel']],
            'blog_category' => ['blogId' => $ids['blog'], 'blogVersionId' => Defaults::LIVE_VERSION, 'categoryId' => $ids['category'], 'categoryVersionId' => Defaults::LIVE_VERSION],
            'blog_category_tree' => ['blogId' => $ids['blog'], 'blogVersionId' => Defaults::LIVE_VERSION, 'categoryId' => $ids['category'], 'categoryVersionId' => Defaults::LIVE_VERSION],
            'blog_tag' => ['blogId' => $ids['blog'], 'blogVersionId' => Defaults::LIVE_VERSION, 'tagId' => $ids['tag']],
        ];
    }

    /**
     * @param list<string> $ids
     */
    private function assertMappingCount(string $entityName, string $property, array $ids, int $expected, Context $context, string $scope): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($property, $ids));

        static::assertCount(
            $expected,
            $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
            'Unexpected ' . $entityName . ' rows for ' . $scope,
        );
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
