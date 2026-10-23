<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ResetOnClone;

/**
 * @internal
 */
#[CoversClass(ResetOnClone::class)]
class ResetOnCloneTest extends TestCase
{
    public function testParse(): void
    {
        static::assertSame(['reset_on_clone' => true], iterator_to_array((new ResetOnClone())->parse()));
    }
}
