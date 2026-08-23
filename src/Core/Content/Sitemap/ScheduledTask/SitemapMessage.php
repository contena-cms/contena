<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ScheduledTask;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

class SitemapMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?string $lastChannelId,
        private readonly ?string $lastLanguageId,
        private readonly ?string $lastProvider,
        private readonly ?int $nextOffset,
        private readonly bool $finished,
        private readonly ?string $tenantId,
    ) {
    }

    public function getLastChannelId(): ?string
    {
        return $this->lastChannelId;
    }

    public function getLastLanguageId(): ?string
    {
        return $this->lastLanguageId;
    }

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getNextOffset(): ?int
    {
        return $this->nextOffset;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }
}
