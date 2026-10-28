<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppShippingMethod;

use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppShippingMethodDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_shipping_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppShippingMethodEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.7.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('app_name', 'appName'))->addFlags(new Required()),
            (new StringField('identifier', 'identifier'))->addFlags(new Required()),

            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('shippingMethod', 'shipping_method_id', 'id', ShippingMethodDefinition::class, false),

            new FkField('original_media_id', 'originalMediaId', MediaDefinition::class),
            new ManyToOneAssociationField('originalMedia', 'original_media_id', MediaDefinition::class),
        ]);
    }
}
