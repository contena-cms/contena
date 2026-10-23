<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class AdminUserConfigDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_user_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminUserConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminUserConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AdminUserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of admin user configuration.'),
            (new FkField('admin_user_id', 'adminUserId', AdminUserDefinition::class))->addFlags(new Required())->setDescription('Unique identity of admin user.'),
            (new StringField('key', 'key'))->addFlags(new Required())->setDescription('Unique key for every userconfig.'),
            (new JsonField('value', 'value'))->setDescription('Value of the admin user configuration.'),

            new ManyToOneAssociationField('admin_user', 'admin_user_id', AdminUserDefinition::class, 'id', false),
        ]);
    }
}
