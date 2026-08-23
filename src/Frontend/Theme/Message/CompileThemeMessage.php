<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Message;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * Used to compile themes in the queue.
 */
class CompileThemeMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $channelId,
        private readonly string $themeId,
        private readonly bool $withAssets,
        private readonly Context $context,
        private readonly bool $assign = false,
    ) {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function isWithAssets(): bool
    {
        return $this->withAssets;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Whether to assign the theme to the channel once compiled. Defers a theme switch until its
     * files exist so the frontend never renders without CSS.
     */
    public function isAssign(): bool
    {
        return $this->assign;
    }
}
