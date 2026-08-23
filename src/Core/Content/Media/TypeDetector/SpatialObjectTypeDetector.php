<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\TypeDetector;

use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaType\MediaType;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;

class SpatialObjectTypeDetector implements TypeDetectorInterface
{
    protected const array SUPPORTED_FILE_EXTENSIONS = [
        'glb' => [],
    ];

    public function detect(MediaFile $mediaFile, ?MediaType $previouslyDetectedType): ?MediaType
    {
        $fileExtension = mb_strtolower($mediaFile->getFileExtension());
        if (!\array_key_exists($fileExtension, self::SUPPORTED_FILE_EXTENSIONS)) {
            return $previouslyDetectedType;
        }

        if ($previouslyDetectedType === null) {
            $previouslyDetectedType = new SpatialObjectType();
        }

        $previouslyDetectedType->addFlags(self::SUPPORTED_FILE_EXTENSIONS[$fileExtension]);

        return $previouslyDetectedType;
    }
}
