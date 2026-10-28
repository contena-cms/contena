<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 */
class TestAttributeEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'customer')]
    public ?string $customerId = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: 'product', onDelete: OnDelete::CASCADE)]
    public ?array $products = null;

    #[ManyToOne(entity: 'customer', onDelete: OnDelete::SET_NULL)]
    public ?CustomerEntity $customer;
}
