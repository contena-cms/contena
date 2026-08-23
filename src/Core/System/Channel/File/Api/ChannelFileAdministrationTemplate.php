<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ChannelFileAdministrationTemplate
{
    public function __construct(
        public string $twigNamespace,
        public string $templateName,
        public string $templateContent,
        public string $role,
    ) {
    }
}
