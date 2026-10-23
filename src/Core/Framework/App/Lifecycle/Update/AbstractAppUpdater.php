<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Update;

use Contena\Core\Framework\Context;

/**
 * @internal
 */
abstract class AbstractAppUpdater
{
    abstract public function updateApps(Context $context): void;

    abstract protected function getDecorated(): AbstractAppUpdater;
}
