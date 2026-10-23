<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Demodata\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Demodata\DemodataRequest;
use Contena\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
#[CoversClass(DemodataRequestCreatedEvent::class)]
class DemodataRequestCreatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $request = new DemodataRequest();
        $context = Context::createDefaultContext();
        $input = new ArrayInput([]);

        $event = new DemodataRequestCreatedEvent(
            $request,
            $context,
            $input
        );

        static::assertSame($request, $event->getRequest());
        static::assertSame($context, $event->getContext());
        static::assertSame($input, $event->getInput());
    }
}
