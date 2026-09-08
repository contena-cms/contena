<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MailAttachmentsConfig::class)]
class MailAttachmentsConfigTest extends TestCase
{
    public function testMailAttachmentsConfigInstance(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $extension = new MailSendSubscriberConfig(false);
        $attachmentsConfig = new MailAttachmentsConfig(
            $context,
            $mailTemplate,
            $extension,
        );

        static::assertSame($context, $attachmentsConfig->getContext());
        static::assertSame($mailTemplate, $attachmentsConfig->getMailTemplate());
        static::assertSame($extension, $attachmentsConfig->getExtension());
    }
}
