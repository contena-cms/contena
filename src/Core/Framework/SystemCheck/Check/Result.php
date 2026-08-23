<?php declare(strict_types=1);

namespace Contena\Core\Framework\SystemCheck\Check;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class Result extends Struct
{
    /**
     * @param mixed[] $extra
     */
    public function __construct(
        public readonly string $name,
        public readonly Status $status,
        public readonly string $message,
        public readonly ?bool $healthy = null,
        public readonly array $extra = [],
    ) {
    }
}
