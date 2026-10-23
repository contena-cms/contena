<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Aggregate\SnippetSet;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SnippetSetEntity>
 *
 * @codeCoverageIgnore
 */
class SnippetSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
