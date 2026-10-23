<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[CoversClass(FlowSendMailActionEvent::class)]
class FlowSendMailActionEventTest extends TestCase
{
    public function testEventConstructorParameters(): void
    {
        $context = Context::createDefaultContext();
        $flow = new StorableFlow('foo', $context);

        $expectDataBag = new DataBag(['data' => 'data']);
        $mailTemplate = new MailTemplateEntity();

        $event = new FlowSendMailActionEvent($expectDataBag, $mailTemplate, $flow);

        static::assertSame($context, $event->getContext());
        static::assertSame($expectDataBag, $event->getDataBag());
        static::assertSame($mailTemplate, $event->getMailTemplate());
        static::assertSame($flow, $event->getStorableFlow());
    }
}
