<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Update;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractAppUpdater
{
    abstract public function updateApps(Context $context): void;

    abstract protected function getDecorated(): AbstractAppUpdater;
}
