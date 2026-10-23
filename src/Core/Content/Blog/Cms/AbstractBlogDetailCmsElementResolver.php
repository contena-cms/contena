<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\Log\Package;

abstract class AbstractBlogDetailCmsElementResolver extends AbstractCmsElementResolver
{
    abstract public function getType(): string;

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();

        if ($resolverContext instanceof EntityResolverContext && $resolverContext->getDefinition() instanceof ChannelBlogDefinition) {
            $blogConfig = new FieldConfig('blog', FieldConfig::SOURCE_MAPPED, $resolverContext->getEntity()->get('id'));
            $config->add($blogConfig);
        }

        $blogConfig = $config->get('blog');
        if ($blogConfig === null || $blogConfig->isMapped() || $blogConfig->getValue() === null) {
            return null;
        }

        $criteria = $this->createBestVariantCriteria($blogConfig->getStringValue());

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('blog_' . $slot->getUniqueIdentifier(), ChannelBlogDefinition::class, $criteria);

        return $criteriaCollection;
    }

    abstract public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void;

    protected function getSlotBlog(CmsSlotEntity $slot, ElementDataCollection $result, string $blogId): ?ChannelBlogEntity
    {
        $searchResult = $result->get('blog_' . $slot->getUniqueIdentifier());
        if ($searchResult === null) {
            return null;
        }

        $bestVariant = $searchResult->getEntities()->filterByProperty('parentId', $blogId)->first();

        /** @var ChannelBlogEntity|null $blog */
        $blog = $bestVariant ?? $searchResult->getEntities()->get($blogId);

        return $blog;
    }

    private function createBestVariantCriteria(string $blogId): Criteria
    {
        $criteria = (new Criteria())
            ->addFilter(new OrFilter([
                new EqualsFilter('blog.parentId', $blogId),
                new EqualsFilter('id', $blogId),
            ]))
            ->addAssociation('properties.group')
            ->addGroupField(new FieldGrouping('displayGroup'));

        $criteria->setTitle('cms::blog-detail-static');

        return $criteria;
    }
}
