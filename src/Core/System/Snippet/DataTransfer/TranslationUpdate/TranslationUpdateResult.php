<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\TranslationUpdate;

/**
 * @internal
 */
final readonly class TranslationUpdateResult
{
    /**
     * @param list<string> $updated locales whose translations were downloaded and persisted
     * @param list<string> $skipped locales that were already up to date
     */
    public function __construct(
        public array $updated = [],
        public array $skipped = [],
    ) {
    }
}
