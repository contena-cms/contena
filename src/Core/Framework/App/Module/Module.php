<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Module;

use Contena\Core\Framework\App\Feature\TranslatedString;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final readonly class Module
{
    public function __construct(
        public string $name,
        public TranslatedString $label,
        public ?string $parent,
        public ?string $source,
        public int $position,
    ) {
    }
}
