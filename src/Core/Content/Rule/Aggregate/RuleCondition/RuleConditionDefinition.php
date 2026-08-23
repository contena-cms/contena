<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\Aggregate\RuleCondition;

use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class RuleConditionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'rule_condition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return RuleConditionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return RuleConditionCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return RuleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned rule condition.'),
            new StringField('type', 'type')->addFlags(new Required()),
            new FkField('rule_id', 'ruleId', RuleDefinition::class)->addFlags(new Required()),
            new ParentFkField(self::class),
            new JsonField('value', 'value'),
            new IntField('position', 'position'),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id'),
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
            new CustomFields(),
        ]);
    }
}
