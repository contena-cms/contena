<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Message;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * used to compile the themes in the queue
 */
class CompileThemeMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $channelId,
        private readonly string $themeId,
        private readonly bool $withAssets,
        private readonly Context $context,
        private readonly bool $assign = false
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
     * Whether to assign the theme to the sales channel once compiled (deferred switch).
     */
    public function isAssign(): bool
    {
        return $this->assign;
    }
}
