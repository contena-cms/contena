<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberRecovery;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Member\MemberDefinition;

class MemberRecoveryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'member_recovery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MemberRecoveryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return MemberRecoveryCollection::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return MemberDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the member recovery account.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member recovery.'),
            new StringField('hash', 'hash')->addFlags(new Required())->setDescription('Password hash for member account recovery.'),
            new FkField('member_id', 'memberId', MemberDefinition::class)->addFlags(new Required())->setDescription('Unique identity of the member.'),
            new OneToOneAssociationField('member', 'member_id', 'id', MemberDefinition::class, false),
        ]);
    }
}
