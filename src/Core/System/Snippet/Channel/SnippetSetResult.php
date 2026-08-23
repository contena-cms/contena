<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class SnippetSetResult extends Struct
{
    /**
     * @param array<string, string> $snippets
     */
    public function __construct(
        public string $languageId,
        public string $locale,
        public ?string $fallbackLocale,
        public ?string $snippetSetId,
        public string $hash,
        public array $snippets,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result';
    }
}
