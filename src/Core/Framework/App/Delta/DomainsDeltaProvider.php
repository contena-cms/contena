<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Delta;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system
 */
class DomainsDeltaProvider extends AbstractAppDeltaProvider
{
    final public const DELTA_NAME = 'domains';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    /**
     * @return array<string>
     */
    public function getReport(Manifest $manifest, AppEntity $app): array
    {
        return $manifest->getAllHosts();
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $hosts = $manifest->getAllHosts();

        if (\count($hosts) < 1) {
            return false;
        }

        if (!$app->getAllowedHosts()) {
            return true;
        }

        return array_diff($hosts, $app->getAllowedHosts()) !== [];
    }
}
