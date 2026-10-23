<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Request;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
final class InstallTranslationRequest
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        public array $locales = [],
        public bool $all = false,
        public bool $activate = true,
    ) {
    }
}
