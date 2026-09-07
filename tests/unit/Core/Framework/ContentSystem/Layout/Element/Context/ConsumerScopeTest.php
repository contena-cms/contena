<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConsumerScope::class)]
class ConsumerScopeTest extends TestCase
{
    /**
     * The wire values back both the codec's rejection message and the write descriptor's choice list, so the
     * list and its order are the contract rather than an implementation detail of the enum.
     */
    #[TestDox('exposes the wire value of every case, in declaration order')]
    public function testValuesExposesEveryCaseValue(): void
    {
        static::assertSame(['parent', 'root'], ConsumerScope::values());
    }
}
