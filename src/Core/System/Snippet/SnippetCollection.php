<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SnippetEntity>
 */
class SnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetEntity::class;
    }
}
