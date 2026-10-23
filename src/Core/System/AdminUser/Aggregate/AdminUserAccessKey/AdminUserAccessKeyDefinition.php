<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class AdminUserAccessKeyDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_user_access_key';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AdminUserAccessKeyCollection::class;
    }

    public function getEntityClass(): string
    {
        return AdminUserAccessKeyEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AdminUserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of admin user access key.'),
            (new FkField('admin_user_id', 'adminUserId', AdminUserDefinition::class))->addFlags(new Required())->setDescription('Unique identity of admin user.'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required())->setDescription('Access key to admin api.'),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->addFlags(new Required())->setDescription('Secret key to admin api.'),
            (new DateTimeField('last_usage_at', 'lastUsageAt'))->setDescription('Parameter that indicates when the access key was last accessed.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('admin_user', 'admin_user_id', AdminUserDefinition::class, 'id', false),
        ]);
    }
}
