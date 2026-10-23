<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CloneProtection::class)]
class CloneProtectionTest extends TestCase
{
    public function testAllowsOnlyConfiguredScopes(): void
    {
        $protection = new CloneProtection(Context::SYSTEM_SCOPE);

        static::assertTrue($protection->isAllowed(Context::SYSTEM_SCOPE));
        static::assertFalse($protection->isAllowed(Context::CRUD_API_SCOPE));
        static::assertSame([CloneProtection::PROTECTION], iterator_to_array($protection->parse()));
    }
}
