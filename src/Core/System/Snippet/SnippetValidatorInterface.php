<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet;


/**
 * @deprecated tag:v6.8.0 - Will be removed, use SnippetValidator directly instead
 */
interface SnippetValidatorInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function validate(): array;
}
