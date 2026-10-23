<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Controller\StorybookController;
use Contena\Frontend\Framework\Routing\StorybookRouteScopeAllowList;

/**
 * @internal
 */
#[CoversClass(StorybookRouteScopeAllowList::class)]
class StorybookRouteScopeAllowListTest extends TestCase
{
    private StorybookRouteScopeAllowList $allowList;

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
