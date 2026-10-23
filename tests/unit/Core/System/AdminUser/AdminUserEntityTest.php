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
        $admin_user = new AdminUserEntity();

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

        $admin_user->setStateMachineHistoryEntries($historyEntries);
        $admin_user->setImportExportLogEntries($logEntries);
        $admin_user->setLocaleId('locale-id');
        $admin_user->setAvatarId('avatar-id');
        $admin_user->setUsername('admin');
        $admin_user->setFirstName('Ada');
        $admin_user->setLastName('Lovelace');
        $admin_user->setEmail('ada@example.com');
        $admin_user->setActive(true);
        $admin_user->setLocale($locale);
        $admin_user->setAvatarMedia($avatar);
        $admin_user->setMedia($media);
        $admin_user->setAccessKeys($accessKeys);
        $admin_user->setConfigs($configs);
        $admin_user->setRecoveryUser($recovery);
        $admin_user->setAdmin(true);
        $admin_user->setMcpAllowlist(['tool-a' => true]);
        $admin_user->setAclRoles($aclRoles);
        $admin_user->setTitle('Dr.');
        $admin_user->setCreatedOrders($createdOrders);
        $admin_user->setUpdatedOrders($updatedOrders);
        $admin_user->setCreatedUsers($createdUsers);
        $admin_user->setUpdatedUsers($updatedUsers);
        $admin_user->setTimeZone('Europe/Berlin');

        static::assertSame($historyEntries, $admin_user->getStateMachineHistoryEntries());
        static::assertSame($logEntries, $admin_user->getImportExportLogEntries());
        static::assertSame('locale-id', $admin_user->getLocaleId());
        static::assertSame('avatar-id', $admin_user->getAvatarId());
        static::assertSame('admin', $admin_user->getUsername());
        static::assertSame('Ada', $admin_user->getFirstName());
        static::assertSame('Lovelace', $admin_user->getLastName());
        static::assertSame('ada@example.com', $admin_user->getEmail());
        static::assertTrue($admin_user->getActive());
        static::assertSame($locale, $admin_user->getLocale());
        static::assertSame($avatar, $admin_user->getAvatarMedia());
        static::assertSame($media, $admin_user->getMedia());
        static::assertSame($accessKeys, $admin_user->getAccessKeys());
        static::assertSame($configs, $admin_user->getConfigs());
        static::assertSame($recovery, $admin_user->getRecoveryUser());
        static::assertTrue($admin_user->isAdmin());
        static::assertSame(['tool-a' => true], $admin_user->getMcpAllowlist());
        static::assertSame($aclRoles, $admin_user->getAclRoles());
        static::assertSame('Dr.', $admin_user->getTitle());
        static::assertSame($createdOrders, $admin_user->getCreatedOrders());
        static::assertSame($updatedOrders, $admin_user->getUpdatedOrders());
        static::assertSame($createdUsers, $admin_user->getCreatedUsers());
        static::assertSame($updatedUsers, $admin_user->getUpdatedUsers());
        static::assertSame('Europe/Berlin', $admin_user->getTimeZone());
    }

    /**
     * @param \Closure(AdminUserEntity): void $write
     * @param \Closure(AdminUserEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $admin_user = $this->admin_userWithInternalProperties();
        $write($admin_user);

        static::assertSame($expected, $read($admin_user));
    }

    /**
     * @param \Closure(AdminUserEntity): void $write
     * @param \Closure(AdminUserEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $admin_user = $this->admin_userWithInternalProperties();
        $write($admin_user);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, AdminUserEntity::class));
        $read($admin_user);
    }

    /**
     * @return \Generator<string, array{0: \Closure(AdminUserEntity): void, 1: \Closure(AdminUserEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (AdminUserEntity $admin_user) => $admin_user->setPassword('secret'),
            static fn (AdminUserEntity $admin_user) => $admin_user->getPassword(),
            'secret',
            'password',
        ];

        yield 'storeToken' => [
            static fn (AdminUserEntity $admin_user) => $admin_user->setStoreToken('token'),
            static fn (AdminUserEntity $admin_user) => $admin_user->getStoreToken(),
            'token',
            'storeToken',
        ];
    }

    private function admin_userWithInternalProperties(): AdminUserEntity
    {
        $admin_user = new AdminUserEntity();
        $admin_user->internalSetEntityData(AdminUserDefinition::ENTITY_NAME, new FieldVisibility(['password', 'storeToken']));

        return $admin_user;
    }
}
