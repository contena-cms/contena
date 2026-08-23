<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationDefinition;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;

class DataDictionaryItemDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'data_dictionary_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DataDictionaryItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return DataDictionaryItemEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
            'position' => 1,
            'level' => 1,
            'childCount' => 0,
            'systemLocked' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return DataDictionaryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the data dictionary item.'),
            new FkField('dictionary_id', 'dictionaryId', DataDictionaryDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the owning data dictionary.'),
            new ParentFkField(self::class)->addFlags(new ApiAware())->setDescription('Unique identity of the optional parent dictionary item.'),
            new TreeLevelField('level', 'level')->addFlags(new ApiAware())->setDescription('The hierarchy level maintained by the DAL tree updater.'),
            new TreePathField('path', 'path')->addFlags(new ApiAware())->setDescription('The ancestor path maintained by the DAL tree updater.'),
            new ChildCountField()->addFlags(new ApiAware())->setDescription('The number of direct child dictionary items.'),
            new StringField('code', 'code')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Stable code unique within the owning data dictionary.'),
            new JsonField('value', 'value')->addFlags(new ApiAware())->setDescription('Optional structured value associated with the dictionary item.'),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Display order of the dictionary item.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When `true`, the dictionary item is enabled.'),
            new BoolField('system_locked', 'systemLocked')->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('When `true`, structural fields are protected from user-scope writes.'),
            new CustomFields()->addFlags(new ApiAware()),
            new TranslatedField('label')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware()),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('dictionary', 'dictionary_id', DataDictionaryDefinition::class, 'id')->addFlags(new ApiAware()),
            new TranslationsAssociationField(DataDictionaryItemTranslationDefinition::class, 'data_dictionary_item_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
