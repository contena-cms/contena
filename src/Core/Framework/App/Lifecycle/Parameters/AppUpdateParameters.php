<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Parameters;

/**
 * @internal
 *
 * @codeCoverageIgnore This is a simple DTO and does not require tests
 */
final readonly class AppUpdateParameters
{
    public function __construct(
        public bool $acceptPermissions = true,
        public bool $strictValidation = false
    ) {
    }
}
