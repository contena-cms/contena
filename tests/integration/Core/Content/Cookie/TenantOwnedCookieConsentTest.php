<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cookie;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Channel\CookieConsentLogRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class TenantOwnedCookieConsentTest extends TestCase
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
        $this->tenantA = $this->createTenant('Cookie consent tenant A')->id;
        $this->tenantB = $this->createTenant('Cookie consent tenant B')->id;
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
            $channelId = $scope === 'platform' || $scope === 'global'
                ? TestDefaults::CHANNEL
                : $this->createChannel($scope, $context);
            $ids[$scope] = $this->createConsent($scope, $channelId, $context);
        }

        $expectedCounts = ['platform' => 2, 'tenant-a' => 1, 'tenant-b' => 1, 'global' => 4];
        foreach ($this->contexts as $scope => $context) {
            foreach (['cookie_consent_config_version' => 'version', 'cookie_consent_log' => 'log'] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }
        }

        $expectedTenants = ['platform' => null, 'tenant-a' => $this->tenantA, 'tenant-b' => $this->tenantB, 'global' => null];
        foreach ($ids as $scope => $scopeIds) {
            $this->assertStoredTenant('cookie_consent_config_version', $scopeIds['version'], $expectedTenants[$scope]);
            $this->assertStoredTenant('cookie_consent_log', $scopeIds['log'], $expectedTenants[$scope]);
        }

        $this->repository('cookie_consent_log')->update([[
            'id' => $ids['tenant-a']['log'],
            'consentAction' => CookieConsentLogRoute::ACTION_ACCEPT_REQUIRED,
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(fn () => $this->repository('cookie_consent_log')->update([[
                'id' => $ids['tenant-a']['log'],
                'consentAction' => CookieConsentLogRoute::ACTION_ACCEPT_ALL,
            ]], $this->contexts[$scope]));
            $this->assertWriteRejected(fn () => $this->repository('cookie_consent_config_version')->delete([[
                'id' => $ids['tenant-a']['version'],
            ]], $this->contexts[$scope]));
        }

        $this->assertWriteRejected(fn () => $this->repository('cookie_consent_log')->create([[
            'id' => Uuid::randomHex(),
            'channelId' => $ids['tenant-b']['channel'],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'consentAction' => CookieConsentLogRoute::ACTION_ACCEPT_ALL,
            'acceptedGroups' => [],
            'configHash' => Uuid::randomHex(),
        ]], $this->contexts['tenant-a']));
    }

    public function testRoutePersistsTheTenantFromTheChannelContext(): void
    {
        $channelId = $this->createChannel('route', $this->contexts['tenant-a']);
        $channelContext = static::getContainer()->get(ChannelContextService::class)->get(
            new ChannelContextServiceParameters($channelId, Uuid::randomHex()),
        );
        $route = static::getContainer()->get(CookieConsentLogRoute::class);
        static::assertInstanceOf(CookieConsentLogRoute::class, $route);

        $route->log(new Request(content: (string) json_encode([
            'consentAction' => CookieConsentLogRoute::ACTION_ACCEPT_ALL,
            'acceptedGroups' => [],
        ])), $channelContext);

        $rows = static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT LOWER(HEX(`tenant_id`)) AS `tenant_id` FROM `cookie_consent_log` WHERE `channel_id` = :channelId',
            ['channelId' => Uuid::fromHexToBytes($channelId)],
        );
        static::assertCount(1, $rows);
        static::assertSame($this->tenantA, $rows[0]['tenant_id']);
    }

    /**
     * @return array{version: string, log: string, channel: string}
     */
    private function createConsent(string $scope, string $channelId, Context $context): array
    {
        $versionId = Uuid::randomHex();
        $logId = Uuid::randomHex();
        $hash = 'cookie-consent-' . $scope . '-' . Uuid::randomHex();

        $this->repository('cookie_consent_config_version')->create([[
            'id' => $versionId,
            'configHash' => $hash,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'cookieGroups' => [],
        ]], $context);
        $this->repository('cookie_consent_log')->create([[
            'id' => $logId,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'consentAction' => CookieConsentLogRoute::ACTION_ACCEPT_ALL,
            'acceptedGroups' => [],
            'configHash' => $hash,
        ]], $context);

        return ['version' => $versionId, 'log' => $logId, 'channel' => $channelId];
    }

    private function createChannel(string $scope, Context $context): string
    {
        $default = $this->repository('channel')->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $groupId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $this->repository('member_group')->create([['id' => $groupId, 'name' => 'Cookie group ' . $scope]], $context);
        $this->repository('category')->create([['id' => $categoryId, 'name' => 'Cookie navigation ' . $scope]], $context);
        $this->repository('channel')->create([[
            'id' => $channelId,
            'name' => 'Cookie channel ' . $scope,
            'accessKey' => 'cookie-channel-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $groupId,
            'navigationCategoryId' => $categoryId,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);

        return $channelId;
    }

    private function assertStoredTenant(string $table, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($id)],
        );
        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function assertWriteRejected(\Closure $write): void
    {
        try {
            $write();
            static::fail('Expected tenant write protection');
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
