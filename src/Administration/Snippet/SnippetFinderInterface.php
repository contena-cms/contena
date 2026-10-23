<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\Log\Package;

interface SnippetFinderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array;
}
