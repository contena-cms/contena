<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

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
        $evenConfig = [];
        $orderId = Uuid::randomHex();

        $attachmentsConfig = new MailAttachmentsConfig(
            $context,
            $mailTemplate,
            $extension,
            $evenConfig,
            $orderId
        );

        static::assertSame($context, $attachmentsConfig->getContext());
        static::assertSame($mailTemplate, $attachmentsConfig->getMailTemplate());
        static::assertSame($extension, $attachmentsConfig->getExtension());
        static::assertSame($evenConfig, $attachmentsConfig->getEventConfig());
        static::assertSame($orderId, $attachmentsConfig->getOrderId());
    }
}
