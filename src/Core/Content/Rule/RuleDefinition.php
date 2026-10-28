<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tag\TagDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return RuleCollection::class;
    }

    public function getEntityClass(): string
    {
        return RuleEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of rule.'),
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required())->setDescription('Name of the rule defined.'),
            (new IntField('priority', 'priority'))->addFlags(new Required())->setDescription('A numerical value to prioritize one of the rules from the list.'),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware())->setDescription('Description of the rule.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new JsonField('module_types', 'moduleTypes'))->setDescription('It can be used in cart or shipping pricing.'),

            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),

            // Reverse Associations not available in sales-channel-api
            (new OneToManyAssociationField('productPrices', ProductPriceDefinition::class, 'rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PRODUCT_AREA)),

            new ManyToManyAssociationField('tags', TagDefinition::class, RuleTagDefinition::class, 'rule_id', 'tag_id'),

            // Promotion References
            (new ManyToManyAssociationField('personaPromotions', PromotionDefinition::class, PromotionPersonaRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
        ]);
    }
}
