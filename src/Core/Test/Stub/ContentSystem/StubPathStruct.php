<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\Struct\Struct;

/**
 * @final
 */
class StubPathStruct extends Struct
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?self $child = null,
        public readonly mixed $nonStructProp = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_path_struct';
    }
}
