<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\SnippetPath;

use Contena\Core\Framework\Log\Package;

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
