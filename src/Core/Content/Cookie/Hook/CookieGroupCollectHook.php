<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Hook;

use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAware;
use Contena\Core\Framework\Script\Execution\Awareness\ChannelContextAwareTrait;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the cookie consent groups are collected for the current sales channel.
 * Allows apps to modify or remove cookie groups and entries, e.g. depending on the payment methods active in the current sales channel.
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.14.0
 *
 * @final
 */
class CookieGroupCollectHook extends Hook implements ChannelContextAware
{
    use ChannelContextAwareTrait;

    final public const HOOK_NAME = 'cookie-group-collect';

    /**
     * @internal
     */
    public function __construct(
        private readonly CookieGroupCollection $cookieGroups,
        ChannelContext $channelContext,
    ) {
        parent::__construct($channelContext->getContext());
        $this->channelContext = $channelContext;
    }

    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->cookieGroups;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            ChannelRepositoryFacadeHookFactory::class,
        ];
    }
}
