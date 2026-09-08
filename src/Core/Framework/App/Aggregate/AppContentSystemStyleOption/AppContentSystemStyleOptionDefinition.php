<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemStyleOption;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class AppContentSystemStyleOptionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_content_system_style_option';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppContentSystemStyleOptionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppContentSystemStyleOptionCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new Required()),
            new StringField('name', 'name')->addFlags(new Required()),
            new JsonField('schema', 'schema')->addFlags(new Required()),
            new StringField('hash', 'hash', 64)->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
