<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

/**
 * @internal
 */
readonly class PresignedUrlResult
{
    public function __construct(
        public string $url,
        public string $path,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
