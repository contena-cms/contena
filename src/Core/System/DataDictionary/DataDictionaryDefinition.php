<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationDefinition;

class DataDictionaryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'data_dictionary';

    final public const string CORE_GENDER = 'core.gender';

    final public const string CORE_REGION_TYPE = 'core.region.type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DataDictionaryCollection::class;
    }

    public function getEntityClass(): string
    {
        return DataDictionaryEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
            'systemLocked' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the data dictionary.'),
            new StringField('technical_name', 'technicalName')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Stable technical name of the data dictionary.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When `true`, the data dictionary is enabled.'),
            new BoolField('system_locked', 'systemLocked')->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('When `true`, structural fields are protected from user-scope writes.'),
            new TranslatedField('label')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new OneToManyAssociationField('items', DataDictionaryItemDefinition::class, 'dictionary_id')->addFlags(new ApiAware(), new CascadeDelete()),
            new TranslationsAssociationField(DataDictionaryTranslationDefinition::class, 'data_dictionary_id')->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
