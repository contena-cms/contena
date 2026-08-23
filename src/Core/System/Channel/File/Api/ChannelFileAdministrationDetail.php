<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ChannelFileAdministrationDetail
{
    /**
     * @param list<ChannelFileAdministrationTemplate> $templates
     */
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $templatePath,
        public string $contentType,
        public array $templates,
        public bool $supportsUserProvidedContent,
        public ?ChannelFileAdministrationConfiguration $configuration,
    ) {
    }
}
