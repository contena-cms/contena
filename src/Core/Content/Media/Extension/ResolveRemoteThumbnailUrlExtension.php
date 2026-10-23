<?php

declare(strict_types=1);

namespace Contena\Core\Content\Media\Extension;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Extensions\Extension;

/**
 * @extends Extension<string|null>
 *
 * @codeCoverageIgnore
 */
final class ResolveRemoteThumbnailUrlExtension extends Extension
{
    public const NAME = 'remote_thumbnail_url.resolve';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        public string $mediaUrl,
        /**
         * @deprecated tag:v6.8.0 - Will be removed, set the value into the `mediaEntity` property instead
         */
        public string $mediaPath,
        public string $width,
        public string $height,
        public string $pattern,
        /**
         * @deprecated tag:v6.8.0 - Will be removed, set the value into the `mediaEntity` property instead
         */
        public ?\DateTimeInterface $mediaUpdatedAt,
        public MediaEntity|PartialEntity $mediaEntity,
    ) {
    }
}
