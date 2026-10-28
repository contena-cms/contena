<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
abstract class AbstractResolvedConfigLoader
{

    /**
     * @return array<string, mixed>
     */
    abstract public function load(string $themeId, SalesChannelContext $context): array;
}
