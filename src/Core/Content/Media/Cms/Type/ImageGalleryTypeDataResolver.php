<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Cms\Type;

use Contena\Core\Framework\Log\Package;

class ImageGalleryTypeDataResolver extends ImageSliderTypeDataResolver
{
    public function getType(): string
    {
        return 'image-gallery';
    }
}
