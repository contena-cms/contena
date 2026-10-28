<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductPrice;

use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductPriceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_price';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductPriceCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductPriceEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductPriceHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the product\'s price.'),
            new VersionField(),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the rule.'),
            (new PriceField('price', 'price'))->addFlags(new Required())->setDescription('Price of the Product.'),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ReverseInherited('prices')),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);

        // @deprecated tag:v6.8.0 - add min value for quantity_start and quantity_end
        if (Feature::isActive('v6.8.0.0')) {
            $fields->add((new IntField('quantity_start', 'quantityStart', 1))->addFlags(new Required())->setDescription('Starting range of quantity of an item.'));
            $fields->add((new IntField('quantity_end', 'quantityEnd', 1))->setDescription('Ending range of quantity of an item.'));
        } else {
            $fields->add((new IntField('quantity_start', 'quantityStart'))->addFlags(new Required())->setDescription('Starting range of quantity of an item.'));
            $fields->add((new IntField('quantity_end', 'quantityEnd'))->setDescription('Ending range of quantity of an item.'));
        }

        return $fields;
    }
}
