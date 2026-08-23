<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Request;

use Contena\Core\Content\MailTemplate\MailTemplateEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class PreviewRequest
{
    /**
     * @param array<string,string> $entityMapping Associative array where the key is the variable name used in the template
     *                                            and the value is the corresponding entity ID.
     * @param array<string,mixed> $templateData Associative array where the key is the variable name used in the template
     *                                          and the value is the corresponding data to be used during rendering.
     */
    public function __construct(
        public MailTemplateEntity $mailTemplate,
        public array $entityMapping = [],
        public array $templateData = [],
        public bool $includeHeaderFooter = false,
        public bool $strictRendering = false,
    ) {
    }
}
