<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ChannelFileAdministrationListItem
{
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $contentType,
        public ?ChannelFileAdministrationConfiguration $configuration,
    ) {
    }
}
