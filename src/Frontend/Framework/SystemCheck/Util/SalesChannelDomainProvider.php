<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDomainProvider extends AbstractSalesChannelDomainProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

}
