<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class AdminSalesChannelApiSource extends SalesChannelApiSource
{
    public string $type = 'admin-sales-channel-api';

    public function __construct(
        string $salesChannelId,
        protected Context $originalContext,
    ) {
        parent::__construct($salesChannelId);
    }

    public function getOriginalContext(): Context
    {
        return $this->originalContext;
    }
}
