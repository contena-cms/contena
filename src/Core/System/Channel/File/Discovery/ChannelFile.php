<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Discovery;

/**
 * @codeCoverageIgnore Simple value object without behavior.
 */
final readonly class ChannelFile
{
    public const TEMPLATE_ROOT = 'files';

    public const DEFAULT_FILE_FAMILY = 'agentic';

    public const TEMPLATE_SUFFIX = '.twig';

    /**
     * @param array<string, string> $templates Twig namespace mapped to resolved template name
     * @param list<string> $templatePaths Case variants of the registered template path, canonical path first
     */
    public function __construct(
        public string $fileFamily,
        public string $fileName,
        public string $templatePath,
        public string $contentType,
        public string $baseTemplateName,
        public array $templates,
        public array $templatePaths = [],
    ) {
    }
}
