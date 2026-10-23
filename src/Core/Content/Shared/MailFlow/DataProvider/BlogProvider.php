<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 *
 * @extends AbstractProvider<BlogEntity, BlogCollection>
 */
class BlogProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return BlogDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
