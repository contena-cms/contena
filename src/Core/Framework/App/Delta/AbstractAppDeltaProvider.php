<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Delta;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractAppDeltaProvider
{
    abstract public function getDeltaName(): string;

    /**
     * @return array<array-key, mixed>
     */
    abstract public function getReport(Manifest $manifest, AppEntity $app): array;

    abstract public function hasDelta(Manifest $manifest, AppEntity $app): bool;
}
