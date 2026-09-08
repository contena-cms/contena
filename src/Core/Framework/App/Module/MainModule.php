<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Module;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
final readonly class MainModule
{
    public function __construct(
        public string $source,
    ) {
    }
}
