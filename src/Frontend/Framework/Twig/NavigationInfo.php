<?php

declare(strict_types=1);

namespace Contena\Storefront\Framework\Twig;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('discovery')]
final readonly class NavigationInfo
{
    /**
     * @param list<string> $pathIdList
     */
    public function __construct(
        public string $id,
        public array $pathIdList,
    ) {
    }
}
