<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\Struct\Struct;

/**
 * @final
 */
class StubContextStruct extends Struct
{
    public function __construct(
        public readonly ?string $cover = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_context_struct';
    }
}
