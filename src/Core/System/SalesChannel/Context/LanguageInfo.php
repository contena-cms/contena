<?php

declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Context;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class LanguageInfo
{
    public function __construct(
        public string $name,
        public string $localeCode,
    ) {
    }
}
