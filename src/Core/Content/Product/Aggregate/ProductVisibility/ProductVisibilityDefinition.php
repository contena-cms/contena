<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductVisibility;

use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductVisibilityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_visibility';

    final public const VISIBILITY_LINK = 10;

    final public const VISIBILITY_SEARCH = 20;

    final public const VISIBILITY_ALL = 30;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductVisibilityEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductVisibilityCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductVisibilityHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey())->setDescription('Unique identity of product visibility.'),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the sales channel.'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
