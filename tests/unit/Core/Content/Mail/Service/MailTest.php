<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\Mail;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(Mail::class)]
class MailTest extends TestCase
{
    public function testMailInstance(): void
    {
        $mail = new Mail();
        $mail->addAttachmentUrl('foobar');

        static::assertSame(['foobar'], $mail->getAttachmentUrls());

        $attachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false),
        );

        $mail->setMailAttachmentsConfig($attachmentsConfig);

        static::assertSame($attachmentsConfig, $mail->getMailAttachmentsConfig());
    }
}
