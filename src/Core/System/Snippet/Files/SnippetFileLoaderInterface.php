<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

interface SnippetFileLoaderInterface
{
    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void;
}
