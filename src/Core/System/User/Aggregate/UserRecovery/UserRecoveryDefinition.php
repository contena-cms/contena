<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserRecovery;

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
use Contena\Core\System\User\UserDefinition;

class UserRecoveryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'user_recovery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UserRecoveryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UserRecoveryCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return UserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of user recovery.'),
            new StringField('hash', 'hash')->removeFlag(ApiAware::class)->addFlags(new Required())->setDescription('Password hash for user recovery.'),
            new FkField('user_id', 'userId', UserDefinition::class)->addFlags(new Required())->setDescription('Unique identity of user.'),
            new CreatedAtField()->addFlags(new Required()),

            new OneToOneAssociationField('user', 'user_id', 'id', UserDefinition::class, false),
        ]);
    }
}
