<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig\Extension;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Util\UrlEncoder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UrlEncodingTwigFilter extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters()
    {
        return [
            new TwigFilter('sw_encode_url', $this->encodeUrl(...)),
            new TwigFilter('sw_encode_media_url', $this->encodeMediaUrl(...)),
        ];
    }

    public function encodeUrl(?string $mediaUrl): ?string
    {
        return UrlEncoder::encodeUrl($mediaUrl);
    }

    /**
     * Accepts the base `Entity` so it also serves `PartialEntity` instances from partial listing
     * loading, which are not `MediaEntity` but expose the same `url`/`path` fields via generic access.
     * A runtime guard still ensures the entity actually is media before reading those fields.
     */
    public function encodeMediaUrl(?Entity $media): ?string
    {
        if ($media === null) {
            return null;
        }

        if (!$media instanceof MediaEntity && $media->getInternalEntityName() !== MediaDefinition::ENTITY_NAME) {
            return null;
        }

        if (!$this->hasFile($media)) {
            return null;
        }

        $url = $media->has('url') ? $media->get('url') : null;

        if (!\is_string($url)) {
            return null;
        }

        return $url;
    }

    private function hasFile(Entity $media): bool
    {
        if (($media->has('path') ? $media->get('path') : null) !== null) {
            return true;
        }

        return ($media->has('mimeType') ? $media->get('mimeType') : null) !== null
            && ($media->has('fileExtension') ? $media->get('fileExtension') : null) !== null
            && ($media->has('fileName') ? $media->get('fileName') : null) !== null;
    }
}
