<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
abstract class StoreStruct extends Struct
{
    /**
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): self;
}
