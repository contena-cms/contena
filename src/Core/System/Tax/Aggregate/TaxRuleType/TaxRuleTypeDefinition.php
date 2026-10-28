<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\Aggregate\TaxRuleType;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxRuleTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tax_rule_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxRuleTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of tax rule type.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new WriteProtected())->setDescription('Unique name of tax rule type.'),
            (new IntField('position', 'position'))->addFlags(new Required())->setDescription('The order of the tabs of your defined tax rules to be displayed in the storefront by entering numerical values like 1,2,3, etc.'),
            (new TranslatedField('typeName'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new OneToManyAssociationField('rules', TaxRuleDefinition::class, 'tax_rule_type_id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(TaxRuleTypeTranslationDefinition::class, 'tax_rule_type_id'))->addFlags(new Required()),
        ]);
    }
}
