<?php declare(strict_types=1);

namespace Contena\Frontend\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerConcatenatedStylesEvent extends Event
{
    public function __construct(
        private string $concatenatedStyles,
        private readonly string $channelId
    ) {
    }

    public function getConcatenatedStyles(): string
    {
        return $this->concatenatedStyles;
    }

    public function setConcatenatedStyles(string $concatenatedStyles): void
    {
        $this->concatenatedStyles = $concatenatedStyles;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }
}
