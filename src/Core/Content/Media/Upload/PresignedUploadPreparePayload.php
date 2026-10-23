<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Upload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
readonly class PresignedUploadPreparePayload
{
    public function __construct(
        #[Assert\NotBlank]
        public string $fileName = '',
        #[Assert\NotBlank]
        public string $extension = '',
        #[Assert\NotBlank]
        public string $mimeType = '',
        public ?string $mediaFolderId = null,
        public bool $private = false,
        public ?string $mediaId = null,
    ) {
    }
}
