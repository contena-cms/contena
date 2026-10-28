<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Controller\StorybookController;
use Contena\Storefront\Framework\Routing\StorybookRouteScopeAllowList;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookRouteScopeAllowList::class)]
class StorybookRouteScopeAllowListTest extends TestCase
{

    protected function setUp(): void
    {
        $this->allowList = new StorybookRouteScopeAllowList();
    }

    public function testAppliesToStorybookController(): void
    {
        static::assertTrue($this->allowList->applies(StorybookController::class));
    }

    public function testDoesNotApplyToOtherControllers(): void
    {
        static::assertFalse($this->allowList->applies(\stdClass::class));
    }
}
