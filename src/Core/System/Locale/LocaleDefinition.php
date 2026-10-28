<?php declare(strict_types=1);

namespace Contena\Core\System\Locale;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LocaleDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'locale';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LocaleCollection::class;
    }

    public function getEntityClass(): string
    {
        return LocaleEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of locale.'),
            (new TranslatedField('territory'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('languages', LanguageDefinition::class, 'locale_id', 'id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(LocaleTranslationDefinition::class, 'locale_id'))->addFlags(new Required()),

            // Reverse Associations not available in sales-channel-api
            (new OneToManyAssociationField('users', UserDefinition::class, 'locale_id', 'id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
