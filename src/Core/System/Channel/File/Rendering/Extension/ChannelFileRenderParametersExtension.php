<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Rendering\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;

/**
 * @public this class is used as type-hint for event listeners, so the class string is "public consumable" API
 *
 * @description Allows adding file-specific Twig parameters while rendering a channel file.
 * Subscribe to `ChannelFileRenderParametersExtension::onPost()` and add custom values to `$extension->result`.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<array<string, mixed>>
 */
final class ChannelFileRenderParametersExtension extends Extension
{
    public const NAME = 'channel-file.render-parameters';

    /**
     * @internal Contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The channel file currently being rendered
         */
        public readonly ChannelFile $file,

        /**
         * @public
         *
         * @description The current channel context
         */
        public readonly ChannelContext $context,

        /**
         * @public
         *
         * @description The channel entity loaded for rendering
         */
        public readonly ChannelEntity $channel,
    ) {
    }
}
