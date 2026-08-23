<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
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
class TenantOwnedChannelAggregateTest extends TestCase
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
        $this->tenantA = $this->createTenant('Channel aggregate tenant A')->id;
        $this->tenantB = $this->createTenant('Channel aggregate tenant B')->id;
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
            $ids[$scope] = $this->createChannelAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach ([
                'channel' => 'channel',
                'channel_analytics' => 'analytics',
                'channel_domain' => 'domain',
                'channel_file' => 'file',
            ] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $this->assertMappingCount('channel_translation', 'channelId', array_column($ids, 'channel'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount('channel_language', 'channelId', array_column($ids, 'channel'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount('channel_country', 'channelId', array_column($ids, 'channel'), $expectedCounts[$scope], $context, $scope);
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $expectedTenant = $expectedTenants[$scope];
            $this->assertStoredTenant('channel', 'id', $scopeIds['channel'], $expectedTenant);
            $this->assertStoredTenant('channel_translation', 'channel_id', $scopeIds['channel'], $expectedTenant);
            $this->assertStoredTenant('channel_analytics', 'id', $scopeIds['analytics'], $expectedTenant);
            $this->assertStoredTenant('channel_domain', 'id', $scopeIds['domain'], $expectedTenant);
            $this->assertStoredTenant('channel_file', 'id', $scopeIds['file'], $expectedTenant);
            $this->assertStoredTenant('channel_language', 'channel_id', $scopeIds['channel'], $expectedTenant);
            $this->assertStoredTenant('channel_country', 'channel_id', $scopeIds['channel'], $expectedTenant);
        }

        $tenantA = $ids['tenant-a'];
        $this->repository('channel_analytics')->update([[
            'id' => $tenantA['analytics'],
            'trackingId' => 'updated-tenant-a',
        ]], $this->contexts['tenant-a']);
        $this->repository('channel_domain')->update([[
            'id' => $tenantA['domain'],
            'hreflangUseOnlyLocale' => true,
        ]], $this->contexts['tenant-a']);
        $this->repository('channel_file')->update([[
            'id' => $tenantA['file'],
            'enabled' => false,
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            foreach ([
                ['channel_analytics', ['id' => $tenantA['analytics'], 'trackingId' => 'rejected-' . $scope]],
                ['channel_domain', ['id' => $tenantA['domain'], 'hreflangUseOnlyLocale' => false]],
                ['channel_file', ['id' => $tenantA['file'], 'enabled' => true]],
            ] as [$entityName, $payload]) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->update([$payload], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }

            $this->assertWriteRejected(
                fn () => $this->repository('channel_language')->delete([[
                    'channelId' => $tenantA['channel'],
                    'languageId' => $tenantA['language'],
                ]], $this->contexts[$scope]),
                'Expected channel_language write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('channel_country')->delete([[
                    'channelId' => $tenantA['channel'],
                    'countryId' => $tenantA['country'],
                ]], $this->contexts[$scope]),
                'Expected channel_country write protection for ' . $scope,
            );
        }

        $this->assertCrossTenantReferencesAreRejected($ids);
    }

    /**
     * @return array{channel: string, analytics: string, domain: string, file: string, language: string, country: string}
     */
    private function createChannelAggregate(string $scope, Context $context): array
    {
        $default = $this->repository('channel')->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $groupId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $analyticsId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $domainId = Uuid::randomHex();
        $fileId = Uuid::randomHex();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        static::assertNotNull($snippetSetId);

        $this->repository('member_group')->create([[
            'id' => $groupId,
            'name' => 'Channel aggregate group ' . $scope,
        ]], $context);
        $this->repository('category')->create([[
            'id' => $categoryId,
            'name' => 'Channel aggregate navigation ' . $scope,
        ]], $context);
        $this->repository('channel_analytics')->create([[
            'id' => $analyticsId,
            'trackingId' => 'tracking-' . $scope,
            'active' => true,
            'anonymizeIp' => true,
        ]], $context);
        $this->repository('channel')->create([[
            'id' => $channelId,
            'name' => 'Channel aggregate ' . $scope,
            'accessKey' => 'channel-aggregate-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $groupId,
            'navigationCategoryId' => $categoryId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'analyticsId' => $analyticsId,
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);
        $this->repository('channel_domain')->create([[
            'id' => $domainId,
            'channelId' => $channelId,
            'languageId' => $default->getLanguageId(),
            'snippetSetId' => $snippetSetId,
            'url' => 'https://' . Uuid::randomHex() . '.example.invalid',
        ]], $context);
        $this->repository('channel_file')->create([[
            'id' => $fileId,
            'channelId' => $channelId,
            'fileFamily' => 'channel-aggregate',
            'fileName' => $scope . '.json',
            'enabled' => true,
        ]], $context);

        return [
            'channel' => $channelId,
            'analytics' => $analyticsId,
            'domain' => $domainId,
            'file' => $fileId,
            'language' => $default->getLanguageId(),
            'country' => $default->getCountryId(),
        ];
    }

    /**
     * @param array<string, array{channel: string, analytics: string, domain: string, file: string, language: string, country: string}> $ids
     */
    private function assertCrossTenantReferencesAreRejected(array $ids): void
    {
        $this->assertWriteRejected(
            fn () => $this->repository('channel_file')->create([[
                'id' => Uuid::randomHex(),
                'channelId' => $ids['tenant-b']['channel'],
                'fileFamily' => 'cross-tenant',
                'fileName' => 'rejected.json',
                'enabled' => true,
            ]], $this->contexts['tenant-a']),
            'Expected a channel file referencing another tenant channel to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('channel_domain')->create([[
                'id' => Uuid::randomHex(),
                'channelId' => $ids['tenant-b']['channel'],
                'languageId' => $ids['tenant-b']['language'],
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://' . Uuid::randomHex() . '.example.invalid',
            ]], $this->contexts['tenant-a']),
            'Expected a channel domain referencing another tenant channel to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('channel')->update([[
                'id' => $ids['tenant-a']['channel'],
                'analyticsId' => $ids['tenant-b']['analytics'],
            ]], $this->contexts['tenant-a']),
            'Expected a channel referencing another tenant analytics row to be rejected',
        );
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
