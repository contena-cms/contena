<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

/**
 * @internal
 */
readonly class FileMetadataResult
{
    public function __construct(
        public int $size,
        public \DateTimeImmutable $lastModified,
        public ?string $etag = null,
        public ?string $contentType = null,
    ) {
    }
}
