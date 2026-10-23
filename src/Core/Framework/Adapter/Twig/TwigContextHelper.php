<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig;

use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;

final class TwigContextHelper
{
    /**
     * @param array<string, mixed> $twigContext
     */
    public static function getContext(array $twigContext): ?Context
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof Context) {
            return $context;
        }

        return self::getChannelContext($twigContext)?->getContext();
    }

    /**
     * @param array<string, mixed> $twigContext
     */
    public static function getChannelContext(array $twigContext): ?ChannelContext
    {
        $context = $twigContext['context'] ?? null;
        if ($context instanceof ChannelContext) {
            return $context;
        }

        $channelContext = $twigContext['channelContext'] ?? null;
        if ($channelContext instanceof ChannelContext) {
            return $channelContext;
        }

        return null;
    }
}
