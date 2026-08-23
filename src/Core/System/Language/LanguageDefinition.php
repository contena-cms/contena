<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationDefinition;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Contena\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationDefinition;
use Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationDefinition;
use Contena\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationDefinition;
use Contena\Core\System\Region\Aggregate\RegionTranslation\RegionTranslationDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Contena\Core\System\StateMachine\StateMachineTranslationDefinition;

class LanguageDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'language';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LanguageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LanguageEntity::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return ['active' => true];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of language.'),
            new ParentFkField(self::class)->addFlags(new ApiAware()),
            new FkField('locale_id', 'localeId', LocaleDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of locale.'),
            new FkField('translation_code_id', 'translationCodeId', LocaleDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of translation code.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required())->setDescription('Name of the language.'),
            new BoolField('active', 'active')->addFlags(new ApiAware(), new Required()),
            new BoolField('translation_auto_update', 'translationAutoUpdate')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields for platform extensions.'),
            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware()),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('translationCode', 'translation_code_id', LocaleDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware()),
            new ManyToManyAssociationField('channels', ChannelDefinition::class, ChannelLanguageDefinition::class, 'language_id', 'channel_id'),
            new OneToManyAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('regionTranslations', RegionTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('organizationTranslations', OrganizationTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('organizationUnitTranslations', OrganizationUnitTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('positionTranslations', PositionTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('dataDictionaryTranslations', DataDictionaryTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('dataDictionaryItemTranslations', DataDictionaryItemTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('mailHeaderFooterTranslations', MailHeaderFooterTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('mailTemplateTranslations', MailTemplateTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('mailTemplateTypeTranslations', MailTemplateTypeTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('pluginTranslations', PluginTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineTranslations', StateMachineTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineStateTranslations', StateMachineStateTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('numberRangeTypeTranslations', NumberRangeTypeTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('numberRangeTranslations', NumberRangeTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete()),
        ]);
    }
}
