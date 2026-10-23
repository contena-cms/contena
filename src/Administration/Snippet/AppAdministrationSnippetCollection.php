<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppAdministrationSnippetEntity>
 *
 * @codeCoverageIgnore
 */
class AppAdministrationSnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'administration_snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppAdministrationSnippetEntity::class;
    }
}
