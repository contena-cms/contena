<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\TypeDetector;

use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaType\MediaType;

interface TypeDetectorInterface
{
    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType;
}
