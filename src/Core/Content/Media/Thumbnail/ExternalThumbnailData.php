<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Thumbnail;

use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
readonly class ExternalThumbnailData
{
    public function __construct(
        public string $url,
        /**
         * @var int<1, max> $width
         */
        public int $width,
        /**
         * @var int<1, max> $height
         */
        public int $height
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!MediaUploadService::isExternalUrl($this->url)) {
            throw MediaException::invalidUrl($this->url);
        }

        if ($this->width <= 0) {
            throw MediaException::invalidDimension('width', $this->width);
        }

        if ($this->height <= 0) {
            throw MediaException::invalidDimension('height', $this->height);
        }
    }
}
