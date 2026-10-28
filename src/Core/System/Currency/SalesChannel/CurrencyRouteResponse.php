<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CurrencyCollection>
 */
#[Package('fundamentals@framework')]
class CurrencyRouteResponse extends StoreApiResponse
{
    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
