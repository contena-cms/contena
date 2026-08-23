<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\Struct\Struct;

/**
 * @final
 */
class StubStruct extends Struct
{
    public function getApiAlias(): string
    {
        return 'test_struct';
    }
}
