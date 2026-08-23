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
    public const string NAME = 'remote_thumbnail_url.resolve';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        public string $mediaUrl,
        public string $width,
        public string $height,
        public string $pattern,
        public MediaEntity|PartialEntity $mediaEntity,
    ) {
    }
}
