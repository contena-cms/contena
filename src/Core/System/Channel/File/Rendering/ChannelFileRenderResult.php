<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Rendering;

/**
 * @codeCoverageIgnore Simple value object without behavior.
 */
final readonly class ChannelFileRenderResult
{
    public function __construct(
        public string $fileName,
        public string $content,
        public string $contentType,
    ) {
    }
}
