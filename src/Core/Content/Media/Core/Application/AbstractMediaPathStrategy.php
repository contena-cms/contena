<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Application;

use Contena\Core\Content\Media\Core\Params\MediaLocationStruct;
use Contena\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Util\Hasher;

/**
 * Global path generator for media files
 *
 * A media path strategy is responsible to generate the path for a media or thumbnail.
 * It has to define a unique name, which can be used to configure this strategy in the configuration.
 */
abstract class AbstractMediaPathStrategy
{
    /**
     * The function has to generate the path for the provided locations
     *
     * Called when the media was uploaded or when the media will be renamed.
     *
     * @param array<MediaLocationStruct|ThumbnailLocationStruct> $locations Contains a mix of media and thumbnail file locations. The locations are build over the database or by the request when the media was uploaded or renamed
     *
     * @return array<string, string> indexed by id, value contains the path (e.g. media/0a/test.jpg, thumbnail/0a/test_100x100.jpg)
     */
    public function generate(array $locations): array
    {
        $paths = [];
        foreach ($locations as $location) {
            // filter out locations without a file name (upload canceled)
            if (!$this->hasFile($location)) {
                continue;
            }

            $type = match (true) {
                $location instanceof MediaLocationStruct => 'media',
                $location instanceof ThumbnailLocationStruct => 'thumbnail',
                default => throw MediaException::unknownLocationType(),
            };

            $paths[$location->id] = implode('/', \array_filter([
                $type,
                $this->tenantShard($location),
                $this->md5($this->value($location)),
                $this->cacheBuster($location),
                $this->physicalFilename($location),
            ]));
        }

        return $paths;
    }

    /**
     * Returns a unique name which is used for the configuration to identify this strategy
     */
    abstract public function name(): string;

    protected function physicalFilename(MediaLocationStruct|ThumbnailLocationStruct $location): string
    {
        $filenameSuffix = $location instanceof ThumbnailLocationStruct ? \sprintf('_%dx%d', $location->width, $location->height) : '';

        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        $extension = $media->extension ? '.' . $media->extension : '';

        return $media->fileName . $filenameSuffix . $extension;
    }

    protected function cacheBuster(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $media = $location instanceof MediaLocationStruct ? $location : $location->media;

        return $media->uploadedAt ? (string) $media->uploadedAt->getTimestamp() : null;
    }

    protected function md5(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $hash = Hasher::hash($value, 'md5');

        $slices = \array_slice(str_split($hash, 2), 0, 3);
        $slices = array_map(
            fn ($slice) => \array_key_exists($slice, $this->replaceCharacters()) ? $this->replaceCharacters()[$slice] : $slice,
            $slices
        );

        return implode('/', $slices);
    }

    /**
     * Returned value will be used by default for md5 hashing
     */
    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return null;
    }

    /**
     * `replaceCharacters` allows to define a list of characters which should be replaced by a replacement character.
     *
     * @return array<string, string>
     */
    protected function replaceCharacters(): array
    {
        return [
            'ad' => 'g0',
        ];
    }

    /**
     * Prefixes tenant media files with a tenant shard directory, so every
     * tenant gets its own subtree inside the media folder. Platform-global
     * files (no tenant) keep the legacy layout.
     */
    private function tenantShard(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $tenantId = $location instanceof ThumbnailLocationStruct
            ? $location->media->tenantId
            : $location->tenantId;

        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        return 'tenant/' . substr($tenantId, 0, 2) . '/' . $tenantId;
    }

    private function hasFile(ThumbnailLocationStruct|MediaLocationStruct $location): bool
    {
        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        return $media->fileName !== null && $media->extension !== null;
    }
}
