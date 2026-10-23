<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

use Contena\Core\Framework\Log\Package;

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
