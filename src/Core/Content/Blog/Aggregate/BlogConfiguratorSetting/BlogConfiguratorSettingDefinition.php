<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogConfiguratorSettingDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_configurator_setting';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogConfiguratorSettingCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogConfiguratorSettingEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogConfiguratorSettingHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of Blog configurator setting.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media.'),
            (new FkField('property_group_option_id', 'optionId', PropertyGroupOptionDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of option.'),
            (new JsonField('price', 'price'))->setDescription('Price of the Blog.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order of the tabs of your defined blog configuration settings in the frontend by entering numerical values like 1,2,3, etc.'),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('option', 'property_group_option_id', PropertyGroupOptionDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
