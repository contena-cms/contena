<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 */
#[CoversClass(AdminUserEntity::class)]
class AdminUserEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $adminUser = new AdminUserEntity();

        $historyEntries = new StateMachineHistoryCollection();
        $logEntries = new ImportExportLogCollection();
        $locale = new LocaleEntity();
        $avatar = new MediaEntity();
        $media = new MediaCollection();
        $accessKeys = new AdminUserAccessKeyCollection();
        $configs = new AdminUserConfigCollection();
        $recovery = new AdminUserRecoveryEntity();
        $aclRoles = new AclRoleCollection();
        $createdOrders = new OrderCollection();
        $updatedOrders = new OrderCollection();
        $createdUsers = new UserCollection();
        $updatedUsers = new UserCollection();

        $adminUser->setStateMachineHistoryEntries($historyEntries);
        $adminUser->setImportExportLogEntries($logEntries);
        $adminUser->setLocaleId('locale-id');
        $adminUser->setAvatarId('avatar-id');
        $adminUser->setUsername('admin');
        $adminUser->setName('Ada');
        $adminUser->setLastName('Lovelace');
        $adminUser->setEmail('ada@example.com');
        $adminUser->setActive(true);
        $adminUser->setLocale($locale);
        $adminUser->setAvatarMedia($avatar);
        $adminUser->setMedia($media);
        $adminUser->setAccessKeys($accessKeys);
        $adminUser->setConfigs($configs);
        $adminUser->setRecoveryUser($recovery);
        $adminUser->setAdmin(true);
        $adminUser->setMcpAllowlist(['tool-a' => true]);
        $adminUser->setAclRoles($aclRoles);
        $adminUser->setTitle('Dr.');
        $adminUser->setCreatedOrders($createdOrders);
        $adminUser->setUpdatedOrders($updatedOrders);
        $adminUser->setCreatedUsers($createdUsers);
        $adminUser->setUpdatedUsers($updatedUsers);
        $adminUser->setTimeZone('Europe/Berlin');

        static::assertSame($historyEntries, $adminUser->getStateMachineHistoryEntries());
        static::assertSame($logEntries, $adminUser->getImportExportLogEntries());
        static::assertSame('locale-id', $adminUser->getLocaleId());
        static::assertSame('avatar-id', $adminUser->getAvatarId());
        static::assertSame('admin', $adminUser->getUsername());
        static::assertSame('Ada', $adminUser->getName());
        static::assertSame('Lovelace', $adminUser->getLastName());
        static::assertSame('ada@example.com', $adminUser->getEmail());
        static::assertTrue($adminUser->getActive());
        static::assertSame($locale, $adminUser->getLocale());
        static::assertSame($avatar, $adminUser->getAvatarMedia());
        static::assertSame($media, $adminUser->getMedia());
        static::assertSame($accessKeys, $adminUser->getAccessKeys());
        static::assertSame($configs, $adminUser->getConfigs());
        static::assertSame($recovery, $adminUser->getRecoveryUser());
        static::assertTrue($adminUser->isAdmin());
        static::assertSame(['tool-a' => true], $adminUser->getMcpAllowlist());
        static::assertSame($aclRoles, $adminUser->getAclRoles());
        static::assertSame('Dr.', $adminUser->getTitle());
        static::assertSame($createdOrders, $adminUser->getCreatedOrders());
        static::assertSame($updatedOrders, $adminUser->getUpdatedOrders());
        static::assertSame($createdUsers, $adminUser->getCreatedUsers());
        static::assertSame($updatedUsers, $adminUser->getUpdatedUsers());
        static::assertSame('Europe/Berlin', $adminUser->getTimeZone());
    }

    /**
     * @param \Closure(AdminUserEntity): void $write
     * @param \Closure(AdminUserEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $adminUser = $this->admin_userWithInternalProperties();
        $write($adminUser);

        static::assertSame($expected, $read($adminUser));
    }

    /**
     * @param \Closure(AdminUserEntity): void $write
     * @param \Closure(AdminUserEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $adminUser = $this->admin_userWithInternalProperties();
        $write($adminUser);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, AdminUserEntity::class));
        $read($adminUser);
    }

    /**
     * @return \Generator<string, array{0: \Closure(AdminUserEntity): void, 1: \Closure(AdminUserEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (AdminUserEntity $adminUser) => $adminUser->setPassword('secret'),
            static fn (AdminUserEntity $adminUser) => $adminUser->getPassword(),
            'secret',
            'password',
        ];

        yield 'storeToken' => [
            static fn (AdminUserEntity $adminUser) => $adminUser->setStoreToken('token'),
            static fn (AdminUserEntity $adminUser) => $adminUser->getStoreToken(),
            'token',
            'storeToken',
        ];
    }

    private function admin_userWithInternalProperties(): AdminUserEntity
    {
        $adminUser = new AdminUserEntity();
        $adminUser->internalSetEntityData(AdminUserDefinition::ENTITY_NAME, new FieldVisibility(['password', 'storeToken']));

        return $adminUser;
    }
}
