<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Aggregate\FlowSequence;

use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
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
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class FlowSequenceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'flow_sequence';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowSequenceCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowSequenceEntity::class;
    }

    public function getDefaults(): array
    {
        return ['trueCase' => false, 'position' => 1];
    }

    public function since(): ?string
    {
        return '6.4.6.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return FlowDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of flow sequence.'),
            (new FkField('flow_id', 'flowId', FlowDefinition::class))->addFlags(new Required())->setDescription('Unique identity of flow.'),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->setDescription('Unique identity of rule.'),
            (new JsonField('config', 'config', [], []))->setDescription('Specifies detailed information about the component.'),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined flow sequence is to be displayed.'),
            (new IntField('display_group', 'displayGroup'))->setDescription('The group to which the flow sequence is visible.'),
            new BoolField('true_case', 'trueCase'),
            new ManyToOneAssociationField('flow', 'flow_id', FlowDefinition::class, 'id', false),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', false),
            (new ParentAssociationField(self::class, 'id'))->setDescription('Unique identity of flow sequence.'),
            new ChildrenAssociationField(self::class),
            new ParentFkField(self::class),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new FkField('app_flow_action_id', 'appFlowActionId', AppFlowActionDefinition::class),
            new ManyToOneAssociationField('appFlowAction', 'app_flow_action_id', AppFlowActionDefinition::class, 'id', false),
        ]);
    }
}
