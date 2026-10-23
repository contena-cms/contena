<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Currency\CurrencyCollection;

/**
 * @extends ChannelApiResponse<CurrencyCollection>
 */
class CurrencyRouteResponse extends ChannelApiResponse
{
    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
