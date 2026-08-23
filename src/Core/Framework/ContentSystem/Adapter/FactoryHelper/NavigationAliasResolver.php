<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Contena\Core\System\Channel\ChannelContext;

/**
 * Resolves navigation aliases to category IDs from sales channel configuration.
 *
 * Aliases:
 * - main-navigation → navigationCategoryId
 * - service-navigation → serviceCategoryId
 * - footer-navigation → footerCategoryId
 *
 * If the alias is not recognized, it's returned unchanged (assumed to be a UUID).
 *
 * @internal
 *
 * @final
 */
class NavigationAliasResolver
{
    private const ALIAS_MAIN_NAVIGATION = 'main-navigation';
    private const ALIAS_SERVICE_NAVIGATION = 'service-navigation';
    private const ALIAS_FOOTER_NAVIGATION = 'footer-navigation';

    public function resolve(string $alias, ChannelContext $context): string
    {
        $channel = $context->getChannel();

        return match ($alias) {
            self::ALIAS_MAIN_NAVIGATION => $channel->getNavigationCategoryId(),
            self::ALIAS_SERVICE_NAVIGATION => $channel->getServiceCategoryId() ?? $alias,
            self::ALIAS_FOOTER_NAVIGATION => $channel->getFooterCategoryId() ?? $alias,
            default => $alias,
        };
    }
}
