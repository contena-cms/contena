<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Plugin\PluginDefinition;

/**
 * @codeCoverageIgnore
 */
class CustomEntityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_entity';

    public function getCollectionClass(): string
    {
        return CustomEntityCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomEntityEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.9.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom entity.'),
            new StringField('name', 'name')->addFlags(new Required())->setDescription('Unique name of the entity.'),
            new JsonField('fields', 'fields')->addFlags(new Required())->setDescription('Fields in custom entity.'),
            new JsonField('flags', 'flags')->setDescription('Indicators used to specify certain settings or characteristics associated with the custom entity.'),
            new FkField('app_id', 'appId', AppDefinition::class)->setDescription('Unique identity of app.'),
            new FkField('plugin_id', 'pluginId', PluginDefinition::class)->setDescription('Unique identity of plugin.'),
            new BoolField('cms_aware', 'cmsAware')->addFlags(new Runtime()),
            new BoolField('store_api_aware', 'channelApiAware')->addFlags(new Runtime()),
            new BoolField('custom_fields_aware', 'customFieldsAware')->setDescription('Parameter that indicates the areas in which the custom field is supported.'),
            new StringField('label_property', 'labelProperty')->setDescription('Specifies which property or attribute of the custom entity is used.'),
            new DateTimeField('deleted_at', 'deletedAt'),
        ]);
    }
}
