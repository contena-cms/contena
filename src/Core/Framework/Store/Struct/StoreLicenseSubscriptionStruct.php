<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Struct;

class StoreLicenseSubscriptionStruct extends Struct
{
    protected \DateTimeInterface $expirationDate;

    public function getApiAlias(): string
    {
        return 'store_license_subscription';
    }
}
