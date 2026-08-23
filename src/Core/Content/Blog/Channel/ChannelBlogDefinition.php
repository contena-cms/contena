<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

class ChannelBlogDefinition extends BlogDefinition implements ChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return ChannelBlogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelBlogCollection::class;
    }

    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        if (!$this->hasAvailableFilter($criteria)) {
            $criteria->addFilter(new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_LINK));
        }

        if ($criteria->getNestingLevel() === Criteria::ROOT_NESTING_LEVEL && $criteria->getFields() === []) {
            $criteria->addAssociation('cover.media')->addAssociation('openGraphMedia');
        }
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();
        $fields->add(new OneToOneAssociationField('seoCategory', 'seoCategory', 'id', CategoryDefinition::class)->addFlags(new ApiAware(), new Runtime())->setDescription('Main category used for SEO URL generation in the current channel.'));

        return $fields;
    }

    private function hasAvailableFilter(Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof BlogAvailableFilter) {
                return true;
            }
        }

        return false;
    }
}
