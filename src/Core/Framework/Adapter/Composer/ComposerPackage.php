<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Composer;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class ComposerPackage
{
    public function __construct(
        public string $name,
        public string $version,
        public string $prettyVersion,
        public string $path,
    ) {
    }
}
