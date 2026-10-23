<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

class ChannelBlogDefinition extends BlogDefinition implements ChannelDefinitionInterface
{
    private const PRICE_BASELINE = ['unitId', 'referenceUnit', 'purchaseUnit'];

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
            $criteria->addFilter(
                new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_LINK)
            );
        }

        // reviews of other users must not be leaked, therefore the filter is applied on every nesting level
        if ($criteria->hasAssociation('blogReviews')) {
            $association = $criteria->getAssociation('blogReviews');
            $activeReviewsFilter = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
            if ($user = $context->getUser()) {
                $activeReviewsFilter->addQuery(new EqualsFilter('userId', $user->getId()));
            }

            $association->addFilter($activeReviewsFilter);
        }

        if ($criteria->getNestingLevel() !== Criteria::ROOT_NESTING_LEVEL) {
            return;
        }

        if ($criteria->getFields() === []) {
            $criteria
                ->addAssociation('prices')
                ->addAssociation('unit')
                ->addAssociation('deliveryTime')
                ->addAssociation('cover.media')
            ;
        }
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new JsonField('calculated_price', 'calculatedPrice'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['price', 'prices'])))
        );
        $fields->add(
            (new ListField('calculated_prices', 'calculatedPrices'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['prices'])))
        );
        $fields->add(
            (new IntField('calculated_max_purchase', 'calculatedMaxPurchase'))->addFlags(new ApiAware(), new Runtime(['maxPurchase']))
        );
        $fields->add(
            (new JsonField('calculated_cheapest_price', 'calculatedCheapestPrice'))->addFlags(new ApiAware(), new Runtime(\array_merge(self::PRICE_BASELINE, ['cheapestPrice'])))
        );
        $fields->add(
            (new BoolField('is_new', 'isNew'))->addFlags(new ApiAware(), new Runtime(['releaseDate']))
        );
        $fields->add(
            (new OneToOneAssociationField('seoCategory', 'seoCategory', 'id', CategoryDefinition::class))->addFlags(new ApiAware(), new Runtime())->setDescription('Main category used for SEO URL generation in the current sales channel')
        );
        $fields->add(
            (new ObjectField('sortedProperties', 'sortedProperties'))->addFlags(new Runtime(), new ApiAware())
        );

        $fields->add(
            (new ObjectField('measurements', 'measurements'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.1.0'))
        );

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
