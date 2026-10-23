<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

use Contena\Core\Framework\Log\Package;

interface SnippetFileLoaderInterface
{
    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void;
}
