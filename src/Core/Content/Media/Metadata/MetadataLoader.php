<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Metadata;

use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaType\MediaType;
use Contena\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;

class MetadataLoader
{
    /**
     * @internal
     *
     * @param MetadataLoaderInterface[] $metadataLoader
     */
    public function __construct(private readonly iterable $metadataLoader)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadFromFile(MediaFile $mediaFile, MediaType $mediaType): ?array
    {
        $metaData = [];
        foreach ($this->metadataLoader as $loader) {
            if ($loader->supports($mediaType)) {
                $metaData = $loader->extractMetadata($mediaFile->getFileName());
                break;
            }
        }

        if ($mediaFile->getHash()) {
            $metaData['hash'] = $mediaFile->getHash();
        }

        return $metaData ?: null;
    }
}
