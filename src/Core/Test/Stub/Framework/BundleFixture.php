<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Framework;

use Contena\Core\Framework\Bundle;

/**
 * @internal
 */
class BundleFixture extends Bundle
{
    public function __construct(
        string $name,
        string $path
    ) {
        $this->name = $name;
        $this->path = $path;
    }
}
