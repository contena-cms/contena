<?php declare(strict_types=1);

namespace Contena\Core\System\TaxProvider;

use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\TaxProvider\Aggregate\TaxProviderTranslation\TaxProviderTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
class TaxProviderDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tax_provider';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxProviderCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxProviderEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'position' => 1,
            'active' => true,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of tax provider.'),
            (new StringField('identifier', 'identifier'))->addFlags(new Required())->setDescription('Unique identity of tax provider.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the tax providers are available for selection in the frontend.'),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the tax providers are available for selection in the frontend.'),
            (new IntField('priority', 'priority'))->addFlags(new Required(), new ApiAware())->setDescription('A numerical value to prioritize one of the tax providers from the list.'),
            (new StringField('process_url', 'processUrl'))->addFlags(new ApiAware())->setDescription('External URL makes request to get tax info.'),
            (new FkField('availability_rule_id', 'availabilityRuleId', RuleDefinition::class))->setDescription('Unique identity of availability Rule.'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of app.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            new TranslationsAssociationField(TaxProviderTranslationDefinition::class, 'tax_provider_id'),
            (new ManyToOneAssociationField('availabilityRule', 'availability_rule_id', RuleDefinition::class))->addFlags(new RestrictDelete()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
