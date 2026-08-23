<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ThemeConfigChangedEvent extends Event implements ContenaEvent
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $themeId,
        protected array $config,
        private readonly Context $context,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
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
