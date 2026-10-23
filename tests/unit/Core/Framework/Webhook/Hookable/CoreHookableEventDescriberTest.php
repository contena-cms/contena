<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;

/**
 * @internal
 */
#[CoversClass(CoreHookableEventDescriber::class)]
class CoreHookableEventDescriberTest extends TestCase
{
    public function testDescribeReturnsAllStaticHookableEvents(): void
    {
        $describer = new CoreHookableEventDescriber();

        static::assertCount(\count(Hookable::HOOKABLE_EVENTS), $describer->describe());
    }
}
