<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class TenantOwnedSystemConfigTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfig;

    private string $tenantA;

    private string $tenantB;

    private string $channelA;

    private string $channelB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemConfig = static::getContainer()->get(SystemConfigService::class);
        $this->tenantA = $this->createTenant('System config tenant A')->id;
        $this->tenantB = $this->createTenant('System config tenant B')->id;
        $this->channelA = $this->createChannel('system-config-a', Context::createTenantContext($this->tenantA));
        $this->channelB = $this->createChannel('system-config-b', Context::createTenantContext($this->tenantB));
    }

    public function testFourContextReadAndWriteMatrix(): void
    {
        $platform = Context::createDefaultContext();
        $tenantA = Context::createTenantContext($this->tenantA);
        $tenantB = Context::createTenantContext($this->tenantB);
        $global = Context::createGlobalContext();

        $this->systemConfig->set('tenant.matrix.platform', 'platform', context: $platform);
        $this->systemConfig->set('tenant.matrix.default', 'tenant-a-default', context: $tenantA);
        $this->systemConfig->set('tenant.matrix.channel', 'tenant-a-channel', $this->channelA, context: $tenantA);
        $this->systemConfig->set('tenant.matrix.channel', 'tenant-b-channel', $this->channelB, context: $tenantB);

        static::assertSame('platform', $this->systemConfig->get('tenant.matrix.platform', context: $platform));
        static::assertNull($this->systemConfig->get('tenant.matrix.default', context: $platform));
        static::assertSame('tenant-a-default', $this->systemConfig->get('tenant.matrix.default', context: $tenantA));
        static::assertNull($this->systemConfig->get('tenant.matrix.default', context: $tenantB));
        static::assertSame('tenant-a-channel', $this->systemConfig->get('tenant.matrix.channel', $this->channelA, $tenantA));
        static::assertSame('tenant-b-channel', $this->systemConfig->get('tenant.matrix.channel', $this->channelB, $tenantB));
        static::assertSame('tenant-a-channel', $this->systemConfig->get('tenant.matrix.channel', $this->channelA, $global));
        static::assertSame('tenant-b-channel', $this->systemConfig->get('tenant.matrix.channel', $this->channelB, $global));

        $this->assertReadRejected($this->channelA, $platform);
        $this->assertReadRejected($this->channelA, $tenantB);
        $this->assertWriteRejected($this->channelA, $platform);
        $this->assertWriteRejected($this->channelA, $tenantB);
        $this->assertWriteRejected($this->channelA, $global);

        $this->systemConfig->set('tenant.matrix.channel', 'tenant-a-updated', $this->channelA, context: $tenantA);
        static::assertSame('tenant-a-updated', $this->systemConfig->get('tenant.matrix.channel', $this->channelA, $tenantA));
    }

    private function assertReadRejected(string $channelId, Context $context): void
    {
        try {
            $this->systemConfig->get('tenant.matrix.channel', $channelId, $context);
            static::fail('Expected tenant configuration read protection.');
        } catch (SystemConfigException $exception) {
            static::assertSame(SystemConfigException::TENANT_CONTEXT_MISMATCH, $exception->getErrorCode());
        }
    }

    private function assertWriteRejected(string $channelId, Context $context): void
    {
        try {
            $this->systemConfig->set('tenant.matrix.channel', 'rejected', $channelId, context: $context);
            static::fail('Expected tenant configuration write protection.');
        } catch (SystemConfigException $exception) {
            static::assertSame(SystemConfigException::TENANT_CONTEXT_MISMATCH, $exception->getErrorCode());
        }
    }

    private function createChannel(string $accessKey, Context $context): string
    {
        $repository = $this->channelRepository();
        $default = $repository->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $memberGroupId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $this->memberGroupRepository()->create([['id' => $memberGroupId, 'name' => 'System config group ' . $accessKey]], $context);
        static::getContainer()->get('category.repository')->create([['id' => $categoryId, 'name' => 'System config category ' . $accessKey]], $context);

        $repository->create([[
            'id' => $channelId,
            'name' => 'System config channel ' . $accessKey,
            'accessKey' => $accessKey . '-' . bin2hex(random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $categoryId,
            'navigationCategoryVersionId' => $default->getNavigationCategoryVersionId(),
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);

        return $channelId;
    }

    /**
     * @return EntityRepository<ChannelCollection>
     */
    private function channelRepository(): EntityRepository
    {
        return static::getContainer()->get('channel.repository');
    }

    /**
     * @return EntityRepository<MemberGroupCollection>
     */
    private function memberGroupRepository(): EntityRepository
    {
        return static::getContainer()->get('member_group.repository');
    }
}
