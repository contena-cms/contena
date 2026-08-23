<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\Test\TestDefaults;

/**
 * Verifies the tenant isolation mechanism of {@see TenantField} against the
 * first tenant-scoped entity (channel): automatic write injection, automatic
 * read filtering, cross-tenant write protection and independent platform data
 * operation.
 *
 * @internal
 */
class TenantScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Tenant A')->id;
        $this->tenantB = $this->createTenant('Tenant B')->id;
    }

    public function testWritesInheritTheTenantOfTheContext(): void
    {
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));

        $channel = $this->findChannel('channel-a', Context::createGlobalContext());
        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertSame($this->tenantA, $channel->getTenantId());
    }

    public function testReadsAreFilteredByTheTenantOfTheContext(): void
    {
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));
        $this->createChannel('channel-b', Context::createTenantContext($this->tenantB));

        static::assertNotNull($this->findChannel('channel-a', Context::createTenantContext($this->tenantA)));
        static::assertNull($this->findChannel('channel-b', Context::createTenantContext($this->tenantA)));

        static::assertNull($this->findChannel('channel-a', Context::createTenantContext($this->tenantB)));
        static::assertNotNull($this->findChannel('channel-b', Context::createTenantContext($this->tenantB)));
    }

    public function testGlobalContextReadsAcrossTenants(): void
    {
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));
        $this->createChannel('channel-b', Context::createTenantContext($this->tenantB));

        static::assertNotNull($this->findChannel('channel-a', Context::createGlobalContext()));
        static::assertNotNull($this->findChannel('channel-b', Context::createGlobalContext()));
    }

    public function testPlatformContextReadsOnlyPlatformOwnedRows(): void
    {
        $this->createChannel('platform-channel', Context::createDefaultContext());
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));
        $this->createChannel('channel-b', Context::createTenantContext($this->tenantB));

        $context = Context::createDefaultContext();

        static::assertNotNull($this->findChannel('platform-channel', $context));
        static::assertNull($this->findChannel('channel-a', $context));
        static::assertNull($this->findChannel('channel-b', $context));
    }

    public function testPayloadTenantMismatchIsRejected(): void
    {
        static::expectException(WriteException::class);

        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA), ['tenantId' => $this->tenantB]);
    }

    public function testGlobalContextCreatesPlatformOwnedRows(): void
    {
        $this->createChannel('platform-channel', Context::createGlobalContext());

        $channel = $this->findChannel('platform-channel', Context::createGlobalContext());
        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertNull($channel->getTenantId());
        static::assertNull($channel->getTranslations()?->first()?->getTenantId());
        static::assertNull($this->findChannel('platform-channel', Context::createTenantContext($this->tenantA)));
    }

    public function testGlobalContextCanNotCreateTenantOwnedRows(): void
    {
        static::expectException(WriteException::class);

        $this->createChannel('global-created-tenant-channel', Context::createGlobalContext(), ['tenantId' => $this->tenantA]);
    }

    public function testGlobalContextCanNotModifyTenantOwnedRows(): void
    {
        $channelId = $this->createChannel('global-updated-tenant-channel', Context::createTenantContext($this->tenantA));

        static::expectException(WriteException::class);

        $this->channelRepository()->update([[
            'id' => $channelId,
            'shortName' => 'Updated by platform',
        ]], Context::createGlobalContext());
    }

    public function testPlatformContextCanNotModifyTenantOwnedRows(): void
    {
        $channelId = $this->createChannel('platform-updated-tenant-channel', Context::createTenantContext($this->tenantA));

        static::expectException(WriteException::class);

        $this->channelRepository()->update([[
            'id' => $channelId,
            'shortName' => 'Updated by platform',
        ]], Context::createDefaultContext());
    }

    public function testTenantContextCanNotClaimPlatformOwnedRows(): void
    {
        $channelId = $this->createChannel('platform-channel-claim', Context::createGlobalContext());

        static::expectException(WriteException::class);

        $this->channelRepository()->update([[
            'id' => $channelId,
            'shortName' => 'Claimed by tenant',
        ]], Context::createTenantContext($this->tenantA));
    }

    public function testTenantContextCanNotModifyAnotherTenantWithoutPayloadTenantId(): void
    {
        $channelId = $this->createChannel('cross-tenant-update', Context::createTenantContext($this->tenantA));

        static::expectException(WriteException::class);

        $this->channelRepository()->update([[
            'id' => $channelId,
            'shortName' => 'Updated by another tenant',
        ]], Context::createTenantContext($this->tenantB));
    }

    public function testGlobalContextCanDeletePlatformOwnedRows(): void
    {
        $channelId = $this->createChannel('platform-channel-delete', Context::createGlobalContext());

        $this->channelRepository()->delete([['id' => $channelId]], Context::createGlobalContext());

        static::assertNull($this->findChannel('platform-channel-delete', Context::createGlobalContext()));
    }

    public function testGlobalContextCanNotDeleteTenantOwnedRows(): void
    {
        $channelId = $this->createChannel('global-deleted-tenant-channel', Context::createTenantContext($this->tenantA));

        static::expectException(WriteException::class);

        $this->channelRepository()->delete([['id' => $channelId]], Context::createGlobalContext());
    }

    public function testTenantContextCanNotDeletePlatformOwnedRows(): void
    {
        $channelId = $this->createChannel('tenant-deleted-platform-channel', Context::createGlobalContext());

        static::expectException(WriteException::class);

        $this->channelRepository()->delete([['id' => $channelId]], Context::createTenantContext($this->tenantA));
    }

    public function testUpdatesCanNotDropTheStoredTenant(): void
    {
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));
        $channel = $this->findChannel('channel-a', Context::createGlobalContext());
        static::assertInstanceOf(ChannelEntity::class, $channel);

        // An update without an explicit tenant id keeps the stored tenant.
        $this->channelRepository()->update([
            ['id' => $channel->getId(), 'shortName' => 'updated'],
        ], Context::createTenantContext($this->tenantA));

        $channel = $this->findChannel('channel-a', Context::createGlobalContext());
        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertSame($this->tenantA, $channel->getTenantId());
    }

    public function testUpdatesCanNotMoveTheRowToAnotherTenant(): void
    {
        $this->createChannel('channel-a', Context::createTenantContext($this->tenantA));
        $channel = $this->findChannel('channel-a', Context::createGlobalContext());
        static::assertInstanceOf(ChannelEntity::class, $channel);

        static::expectException(WriteException::class);

        $this->channelRepository()->update([
            ['id' => $channel->getId(), 'tenantId' => $this->tenantB],
        ], Context::createTenantContext($this->tenantB));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createChannel(string $accessKey, Context $context, array $extra = []): string
    {
        $default = $this->channelRepository()->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $channelId = Uuid::randomHex();
        $memberGroupId = Uuid::randomHex();
        $this->memberGroupRepository()->create([[
            'id' => $memberGroupId,
            'name' => 'Tenant scope member group ' . $accessKey,
        ]], $context);
        $navigationCategoryId = Uuid::randomHex();
        $this->categoryRepository()->create([[
            'id' => $navigationCategoryId,
            'name' => 'Tenant scope navigation ' . $accessKey,
        ]], $context);

        $this->channelRepository()->create([[
            'id' => $channelId,
            'name' => 'Tenant scope channel ' . $accessKey,
            'accessKey' => $accessKey,
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'navigationCategoryVersionId' => $default->getNavigationCategoryVersionId(),
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
            ...$extra,
        ]], $context);

        return $channelId;
    }

    private function findChannel(string $accessKey, Context $context): ?ChannelEntity
    {
        $channel = $this->channelRepository()->search(
            new Criteria()
                ->addFilter(new EqualsFilter('accessKey', $accessKey))
                ->addAssociation('translations')
                ->setLimit(1),
            $context,
        )->getEntities()->first();

        return $channel instanceof ChannelEntity ? $channel : null;
    }

    /**
     * @return EntityRepository<ChannelCollection>
     */
    private function channelRepository(): EntityRepository
    {
        return static::getContainer()->get('channel.repository');
    }

    /**
     * @return EntityRepository<CategoryCollection>
     */
    private function categoryRepository(): EntityRepository
    {
        return static::getContainer()->get('category.repository');
    }

    /**
     * @return EntityRepository<MemberGroupCollection>
     */
    private function memberGroupRepository(): EntityRepository
    {
        return static::getContainer()->get('member_group.repository');
    }
}
