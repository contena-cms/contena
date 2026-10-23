<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;

class UpdateContext extends InstallContext
{

    public function getUpdatePluginVersion(): string
    {
        return $this->updatePluginVersion;
    }
}
