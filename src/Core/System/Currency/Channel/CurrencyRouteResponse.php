<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\Currency\CurrencyCollection;

/**
 * @extends StoreApiResponse<CurrencyCollection>
 */
class CurrencyRouteResponse extends StoreApiResponse
{
    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
