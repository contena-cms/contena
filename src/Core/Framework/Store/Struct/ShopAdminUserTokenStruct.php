<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class ShopUserTokenStruct extends Struct
{
    public function __construct(
        protected string $token,
        protected \DateTimeInterface $expirationDate,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpirationDate(): \DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function getApiAlias(): string
    {
        return 'store_shop_user_token';
    }
}
