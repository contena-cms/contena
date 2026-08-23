<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow;

use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class FlowDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'flow';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowEntity::class;
    }

    public function getDefaults(): array
    {
        return ['active' => false, 'priority' => 1];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned flow.'),
            new StringField('name', 'name', 255)->addFlags(new Required()),
            new StringField('event_name', 'eventName', 255)->addFlags(new Required()),
            new IntField('priority', 'priority'),
            new BlobField('payload', 'payload')->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new BoolField('invalid', 'invalid')->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new BoolField('active', 'active'),
            new StringField('description', 'description', 500),
            new OneToManyAssociationField('sequences', FlowSequenceDefinition::class, 'flow_id', 'id')->addFlags(new CascadeDelete()),
            new CustomFields(),
        ]);
    }
}
