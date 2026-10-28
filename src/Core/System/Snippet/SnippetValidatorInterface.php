<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet;

use Contena\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use SnippetValidator directly instead
 */
#[Package('discovery')]
interface SnippetValidatorInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function validate(): array;
}
