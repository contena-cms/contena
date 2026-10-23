<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Mock;

use Contena\Core\Framework\Plugin;

/**
 * @internal
 */
class TestPlugin extends Plugin
{
    protected string $name;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
