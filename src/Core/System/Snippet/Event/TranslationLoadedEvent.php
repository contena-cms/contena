<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;

/**
 * Dispatched after the translations for a locale have been downloaded and installed.
 */
class TranslationLoadedEvent implements ContenaEvent
{
    public function __construct(
        private readonly string $locale,
        private readonly Context $context,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
