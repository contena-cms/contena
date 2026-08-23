<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

class ChannelCategoryDefinition extends CategoryDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
    }

    public function getEntityClass(): string
    {
        return ChannelCategoryEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            new StringField('seo_url', 'seoUrl')->addFlags(new ApiAware(), new Runtime(['type', 'linkType', 'internalLink']))
        );

        return $fields;
    }
}
