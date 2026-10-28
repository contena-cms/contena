<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Capability;

use Contena\Core\Framework\Log\Package;

/**
 * Register one implementation per sales channel type id with the tag `contena.sales_channel.type_capabilities`.
 * Types without an implementation get the defaults of this class.
 *
 * @internal
 */
#[Package('discovery')]
abstract class AbstractSalesChannelTypeCapabilities
{
    abstract public function getSalesChannelTypeId(): string;

    public function isTransactional(): bool
    {
        return false;
    }
}
