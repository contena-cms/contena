<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Struct;

use Contena\Core\Framework\Struct\Struct;

class SitemapGenerationResult extends Struct
{
    public function __construct(
        private readonly bool $finish,
        private readonly ?string $provider,
        private readonly ?int $offset,
        private readonly ?string $lastChannelId,
        private readonly string $lastLanguageId
    ) {
    }

    public function isFinish(): bool
    {
        return $this->finish;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLastChannelId(): ?string
    {
        return $this->lastChannelId;
    }

    public function getLastLanguageId(): string
    {
        return $this->lastLanguageId;
    }

    public function getApiAlias(): string
    {
        return 'sitemap_generation_result';
    }
}
