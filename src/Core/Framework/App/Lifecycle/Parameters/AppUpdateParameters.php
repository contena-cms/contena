<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Parameters;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore This is a simple DTO and does not require tests
 */
#[Package('framework')]
final readonly class AppUpdateParameters
{
    public function __construct(
        public bool $acceptPermissions = true
    ) {
    }
}
