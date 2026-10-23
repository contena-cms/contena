<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class InvalidPluralizationStruct extends Struct
{
    public function __construct(
        public readonly string $snippetKey,
        public readonly string $snippetValue,
        public readonly bool $isFixable,
        public readonly string $path,
    ) {
    }
}
