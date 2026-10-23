<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Aggregate\CmsSlot;

use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class CmsSlotDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_slot';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsSlotEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsSlotCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CmsBlockDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of CMS slot.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new ReferenceVersionField(CmsBlockDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new JsonField('fieldConfig', 'fieldConfig'))->addFlags(new Runtime(), new ApiAware())->setDescription('Con info of cms slot'),

            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('It indicates the types of content that can be defined within the slot which includes `image`, `text`, `form`, `blog-listing`, `category-navigation`, `blog-box`, `buy-box`, `sidebar-filter`, etc.'),
            (new StringField('slot', 'slot'))->addFlags(new ApiAware(), new Required())->setDescription('Key-value pair to configure which element to be shown in which slot.'),
            (new LockedField())->addFlags(new ApiAware()),
            (new TranslatedField('config'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new JsonField('data', 'data'))->addFlags(new ApiAware(), new Runtime(), new WriteProtected())->setDescription('Each cms slot (element) has a config that has values defined in the admin. When cms loads, each Resolver class adds the resolved config data to this value.'),

            (new FkField('cms_block_id', 'blockId', CmsBlockDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of CMS block where slot is defined.'),
            (new ManyToOneAssociationField('block', 'cms_block_id', CmsBlockDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CmsSlotTranslationDefinition::class, 'cms_slot_id'))->addFlags(new ApiAware()),
        ]);
    }
}
