<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Mcp\Feature;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;
use Contena\Core\Framework\App\Feature\TranslatedString;

/**
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @phpstan-type McpPromptPayload array{name: string, url: string, label?: array<string, string>, description?: array<string, string>}
 */
readonly class McpPromptConfig implements AppFeatureConfig
{
    public function __construct(
        public string $name,
        public string $url,
        public TranslatedString $label,
        public TranslatedString $description,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return McpPromptPayload
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'label' => $this->label->all(),
            'description' => $this->description->all(),
        ];
    }
}
