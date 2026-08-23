<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

/**
 * @internal
 */
readonly class PresignedUploadPrepareResult
{
    public function __construct(
        public string $mediaId,
        public string $url,
        public string $path,
        public string $expiresAt,
        public bool $isDuplicate,
    ) {
    }
}
