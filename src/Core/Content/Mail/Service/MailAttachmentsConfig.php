<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
class MailAttachmentsConfig
{
    public function __construct(
        private Context $context,
        private MailTemplateEntity $mailTemplate,
        private MailSendSubscriberConfig $extension,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getMailTemplate(): MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function setMailTemplate(MailTemplateEntity $mailTemplate): void
    {
        $this->mailTemplate = $mailTemplate;
    }

    public function getExtension(): MailSendSubscriberConfig
    {
        return $this->extension;
    }

    public function setExtension(MailSendSubscriberConfig $extension): void
    {
        $this->extension = $extension;
    }
}
