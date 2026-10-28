<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Contena\Storefront\Framework\Script\Api\StorefrontHook;
use Contena\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontHook::class)]
class StorefrontHookTest extends TestCase
{
    #[TestDox('Uses the Storefront response factory (with render support), not the core one')]
    public function testGetServiceIdsUsesStorefrontResponseFactory(): void
    {
        $serviceIds = StorefrontHook::getServiceIds();

        static::assertContains(StorefrontScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
        static::assertNotContains(ScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
    }
}
