<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Tag\TagDefinition;

class RuleDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return RuleCollection::class;
    }

    public function getEntityClass(): string
    {
        return RuleEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned rule.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new IntField('priority', 'priority')->addFlags(new Required()),
            new LongTextField('description', 'description')->addFlags(new ApiAware()),
            new BlobField('payload', 'payload')->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new BoolField('invalid', 'invalid')->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new ListField('areas', 'areas')->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            new CustomFields()->addFlags(new ApiAware()),
            new OneToManyAssociationField('conditions', RuleConditionDefinition::class, 'rule_id', 'id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('flowSequences', FlowSequenceDefinition::class, 'rule_id', 'id')->addFlags(new RestrictDelete(), new RuleAreas(RuleAreas::FLOW_AREA)),
            new ManyToManyAssociationField('tags', TagDefinition::class, RuleTagDefinition::class, 'rule_id', 'tag_id'),
        ]);
    }
}
