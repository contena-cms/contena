<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Script\Api;

use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Contena\Frontend\Framework\Script\Api\FrontendHook;
use Contena\Frontend\Framework\Script\Api\FrontendScriptResponseFactoryFacadeHookFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FrontendHook::class)]
class FrontendHookTest extends TestCase
{
    #[TestDox('Uses the Frontend response factory with render support, not the core one')]
    public function testGetServiceIdsUsesFrontendResponseFactory(): void
    {
        $serviceIds = FrontendHook::getServiceIds();

        static::assertContains(FrontendScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
        static::assertNotContains(ScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
    }
}
