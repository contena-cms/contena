<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Framework\Context;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailBeforeSentEvent::class)]
#[CoversClass(MailBeforeValidateEvent::class)]
#[CoversClass(MailSentEvent::class)]
class MailFlowEventsTest extends TestCase
{
    public function testMailEventsExposeTheirFlowValues(): void
    {
        $context = Context::createDefaultContext();
        $beforeValidate = new MailBeforeValidateEvent(['subject' => 'Reset'], $context, ['eventName' => 'test']);
        static::assertSame([
            'data' => ['subject' => 'Reset'],
            'templateData' => ['eventName' => 'test'],
        ], $beforeValidate->getValues());
        static::assertSame(['data', 'templateData'], array_keys($beforeValidate::getAvailableData()->toArray()));

        $beforeSent = new MailBeforeSentEvent(['subject' => 'Reset'], new Email(), $context);
        static::assertSame(['data' => ['subject' => 'Reset']], $beforeSent->getValues());
        static::assertSame(['data', 'message'], array_keys($beforeSent::getAvailableData()->toArray()));

        $sent = new MailSentEvent('Reset', ['user@example.com' => 'User'], ['text/html' => '<p>Reset</p>'], $context);
        static::assertSame([
            'subject' => 'Reset',
            'contents' => ['text/html' => '<p>Reset</p>'],
            'recipients' => ['user@example.com' => 'User'],
        ], $sent->getValues());
        static::assertSame(['subject', 'contents', 'recipients'], array_keys($sent::getAvailableData()->toArray()));
    }
}
