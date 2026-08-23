<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(MailTemplateRenderContextEvent::class)]
class MailTemplateRenderContextEventTest extends TestCase
{
    public function testTemplateDataCanBeExtended(): void
    {
        $context = Context::createDefaultContext();
        $event = new MailTemplateRenderContextEvent(['foo' => 'bar'], $context);
        $event->addTemplateData('baz', 'qux');

        static::assertSame(['foo' => 'bar', 'baz' => 'qux'], $event->getTemplateData());
        static::assertSame($context, $event->getContext());
        $event->setTemplateData(['updated' => true]);

        static::assertSame(['updated' => true], $event->getTemplateData());
    }
}
