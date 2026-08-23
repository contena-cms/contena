<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Struct\Struct;

class ThemeChannel extends Struct
{
    public function __construct(
        protected string $themeId,
        protected string $channelId,
    ) {
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function setThemeId(string $themeId): void
    {
        $this->themeId = $themeId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }
}
