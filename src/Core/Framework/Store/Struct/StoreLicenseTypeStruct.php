<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class StoreLicenseTypeStruct extends Struct
{
    protected string $name;

    public function getApiAlias(): string
    {
        return 'store_license_type';
    }
}
