<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class AdminUserRecoveryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'admin_user_recovery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminUserRecoveryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminUserRecoveryCollection::class;
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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of admin_user recovery.'),
            (new StringField('hash', 'hash'))->removeFlag(ApiAware::class)->addFlags(new Required())->setDescription('Password hash for admin_user recovery.'),
            (new FkField('admin_user_id', 'admin_userId', AdminUserDefinition::class))->addFlags(new Required())->setDescription('Unique identity of admin_user.'),
            (new CreatedAtField())->addFlags(new Required()),

            new OneToOneAssociationField('admin_user', 'admin_user_id', 'id', AdminUserDefinition::class, false),
        ]);
    }
}
