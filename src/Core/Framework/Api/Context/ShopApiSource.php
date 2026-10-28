<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class ShopApiSource extends SalesChannelApiSource
{
    public string $type = 'shop-api';
}
