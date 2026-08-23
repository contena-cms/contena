<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Event;

/**
 * Dispatched after the downloaded translation files and metadata entry for a locale have been removed.
 */
class TranslationRemovedEvent
{
    public function __construct(
        private readonly string $locale,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
