<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Context;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
abstract class AbstractSalesChannelContextFactory
{
    abstract public function getDecorated(): AbstractSalesChannelContextFactory;

    /**
     * @param array<string, string|array<string,bool>|CustomerAddressEntity|null> $options
     */
    abstract public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext;
}
