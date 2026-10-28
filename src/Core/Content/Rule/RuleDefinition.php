<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionSetGroupRule\PromotionSetGroupRuleDefinition;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\TaxProvider\TaxProviderDefinition;

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
            (new BlobField('payload', 'payload'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BoolField('invalid', 'invalid'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('When the boolean value is `true`, the rule is no more available for usage.'),
            (new ListField('areas', 'areas'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Internal field.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new JsonField('module_types', 'moduleTypes'))->setDescription('It can be used in cart or shipping pricing.'),

            (new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', 'id'))->addFlags(new CascadeDelete()),

            // Reverse Associations not available in sales-channel-api
            (new OneToManyAssociationField('productPrices', ProductPriceDefinition::class, 'rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PRODUCT_AREA)),
            (new OneToManyAssociationField('shippingMethodPrices', ShippingMethodPriceDefinition::class, 'rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::SHIPPING_AREA)),
            (new OneToManyAssociationField('shippingMethodPriceCalculations', ShippingMethodPriceDefinition::class, 'calculation_rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::SHIPPING_AREA)),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'availability_rule_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::SHIPPING_AREA)),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'availability_rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PAYMENT_AREA)),
            (new OneToManyAssociationField('personaPromotions', PromotionDefinition::class, 'persona_rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new OneToManyAssociationField('flowSequences', FlowSequenceDefinition::class, 'rule_id', 'id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::FLOW_AREA)),
            (new OneToManyAssociationField('taxProviders', TaxProviderDefinition::class, 'availability_rule_id', 'id'))->addFlags(new RestrictDelete(), new Since('6.5.0.0')),

            new ManyToManyAssociationField('tags', TagDefinition::class, RuleTagDefinition::class, 'rule_id', 'tag_id'),

            // Promotion References
            (new ManyToManyAssociationField('personaPromotions', PromotionDefinition::class, PromotionPersonaRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new ManyToManyAssociationField('orderPromotions', PromotionDefinition::class, PromotionOrderRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new ManyToManyAssociationField('cartPromotions', PromotionDefinition::class, PromotionCartRuleDefinition::class, 'rule_id', 'promotion_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new ManyToManyAssociationField('promotionDiscounts', PromotionDiscountDefinition::class, PromotionDiscountRuleDefinition::class, 'rule_id', 'discount_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new ManyToManyAssociationField('promotionSetGroups', PromotionSetGroupDefinition::class, PromotionSetGroupRuleDefinition::class, 'rule_id', 'setgroup_id'))->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::PROMOTION_AREA)),
        ]);
    }
}
