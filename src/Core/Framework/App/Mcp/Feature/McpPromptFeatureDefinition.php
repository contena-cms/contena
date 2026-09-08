<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Mcp\Feature;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;
use Contena\Core\Framework\App\Feature\AppFeatureDefinition;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Mcp\Mcp;
use Contena\Core\Framework\App\Mcp\Xml\McpPrompt;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<McpPromptConfig>
 *
 * @phpstan-import-type McpPromptPayload from McpPromptConfig
 */
class McpPromptFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'mcp_prompt';

    private const FILE = 'Resources/mcp.xml';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return McpPromptConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        if (!$appFilesystem->has(self::FILE)) {
            return [];
        }

        $prompts = Mcp::createFromXmlFile($appFilesystem->path(self::FILE))->getPrompts()?->getPrompts() ?? [];

        return array_map(
            static function (McpPrompt $prompt) use ($defaultLocale): McpPromptConfig {
                // toArray() fills the default locale translation when it is missing,
                // matching what the app expects to be shown for the shop's default language
                $data = $prompt->toArray($defaultLocale);
                /** @var array<string, string> $label */
                $label = $data['label'];
                /** @var array<string, string> $description */
                $description = $data['description'];

                return new McpPromptConfig(
                    $prompt->getName(),
                    $prompt->getUrl(),
                    new TranslatedString($label),
                    new TranslatedString($description),
                );
            },
            $prompts,
        );
    }

    /**
     * @return McpPromptPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param McpPromptPayload $payload
     */
    public function fromPayload(array $payload): McpPromptConfig
    {
        return new McpPromptConfig(
            $payload['name'],
            $payload['url'],
            new TranslatedString($payload['label'] ?? []),
            new TranslatedString($payload['description'] ?? []),
        );
    }
}
