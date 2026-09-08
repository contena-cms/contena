<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

/**
 * @internal
 */
readonly class ResolvedUrl
{
    public function __construct(
        public string $host,
        public string $ip,
    ) {
    }
}
