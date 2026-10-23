<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class StoreActionStruct extends Struct
{
    protected string $label;

    protected string $externalLink;

    public function getApiAlias(): string
    {
        return 'store_action';
    }
}
