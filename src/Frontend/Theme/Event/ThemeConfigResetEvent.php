<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ThemeConfigResetEvent extends Event implements ContenaEvent
{
    public function __construct(
        private readonly string $themeId,
        private readonly Context $context,
    ) {
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
