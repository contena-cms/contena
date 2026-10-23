<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Files;

use Contena\Core\Framework\Log\Package;

class SnippetFileCollectionFactory
{
    /**
     * @internal
     */
    public function __construct(private readonly SnippetFileLoaderInterface $snippetFileLoader)
    {
    }

    public function createSnippetFileCollection(): SnippetFileCollection
    {
        $collection = new SnippetFileCollection();
        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        return $collection;
    }
}
