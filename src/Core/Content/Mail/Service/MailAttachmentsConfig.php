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
    /**
     * @param mixed[] $eventConfig
     */
    public function __construct(
        private Context $context,
        private MailTemplateEntity $mailTemplate,
        private MailSendSubscriberConfig $extension,
        private array $eventConfig,
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

    /**
     * @return mixed[]
     */
    public function getEventConfig(): array
    {
        return $this->eventConfig;
    }

    /**
     * @param mixed[] $eventConfig
     */
    public function setEventConfig(array $eventConfig): void
    {
        $this->eventConfig = $eventConfig;
    }
}
