<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class AccessTokenStruct extends Struct
{


    public function getShopSecret(): ?string
    {
        return $this->shopSecret;
    }

    public function getApiAlias(): string
    {
        return 'store_access_token';
    }
}
