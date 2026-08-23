<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\SnippetPath;

/**
 * @internal
 */
readonly class SnippetPath
{
    public function __construct(
        public string $location,
        public bool $isLocal = false,
    ) {
    }
}
