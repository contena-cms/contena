<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(Permissions::class)]
class PermissionsTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getPermissions());
        static::assertCount(7, $manifest->getPermissions()->getPermissions());
        static::assertSame([
            'blog' => ['create', 'update', 'delete'],
            'category' => ['delete'],
            'blog_manufacturer' => ['create', 'delete'],
            'tax' => ['create'],
            'language' => ['read'],
            'custom_field_set' => ['update'],
            'order' => ['read'],
        ], $manifest->getPermissions()->getPermissions());

        // tax_processor is implied by the manifest's tax provider and added to the additional privileges
        static::assertSame(['admin_user_change_me', 'tax_processor'], $manifest->getPermissions()->getAdditionalPrivileges());
    }

    public function testAsParsedPrivileges(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getPermissions());
        static::assertCount(17, $manifest->getPermissions()->asParsedPrivileges());
        static::assertSame([
            'blog:create',
            'blog:read',
            'blog:update',
            'blog:delete',
            'category:delete',
            'category:read',
            'blog_manufacturer:create',
            'blog_manufacturer:read',
            'blog_manufacturer:delete',
            'tax:create',
            'tax:read',
            'language:read',
            'custom_field_set:update',
            'custom_field_set:read',
            'order:read',
            'admin_user_change_me',
            // implied by the manifest's tax provider
            'tax_processor',
        ], $manifest->getPermissions()->asParsedPrivileges());
    }

    public function testCrudPermission(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest_crud.xml');

        static::assertNotNull($manifest->getPermissions());
        static::assertCount(8, $manifest->getPermissions()->asParsedPrivileges());
        static::assertSame([
            'blog:create',
            'blog:read',
            'blog:update',
            'blog:delete',
            'category:read',
            'category:create',
            'category:update',
            'category:delete',
        ], $manifest->getPermissions()->asParsedPrivileges());
    }
}
