<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\File;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class ResolvedUrl
{
    public function __construct(
        public string $host,
        public string $ip,
    ) {
    }
}
