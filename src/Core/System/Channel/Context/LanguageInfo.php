<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
final readonly class LanguageInfo
{
    public function __construct(
        public string $name,
        public string $localeCode,
    ) {
    }
}
