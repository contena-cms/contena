<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @final
 */
class MailTemplateRenderContextEvent implements ContenaEvent
{
    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private array $templateData,
        private readonly Context $context,
        private readonly ?ChannelEntity $channel = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    public function addTemplateData(string $key, mixed $value): void
    {
        $this->templateData[$key] = $value;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }
}
