<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Integration\IntegrationDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class StateMachineHistoryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'state_machine_history';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineHistoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineHistoryCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of state machine history.'),
            (new IdField('referenced_id', 'referencedId'))->addFlags(new Required())->setDescription('Unique identity of reference.'),
            (new IdField('referenced_version_id', 'referencedVersionId'))->addFlags(new Required())->setDescription('Unique identity of reference\'s version.'),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->addFlags(new Required())->setDescription('Unique identity of state machine.'),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, 'id', false),

            (new StringField('entity_name', 'entityName'))->addFlags(new Required())->setDescription('Name of the entity.'),

            (new FkField('from_state_id', 'fromStateId', StateMachineStateDefinition::class))->addFlags(new Required())->setDescription('Unique identity of fromState.'),
            (new ManyToOneAssociationField('fromStateMachineState', 'from_state_id', StateMachineStateDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('to_state_id', 'toStateId', StateMachineStateDefinition::class))->addFlags(new Required())->setDescription('Unique identity of toState.'),
            (new ManyToOneAssociationField('toStateMachineState', 'to_state_id', StateMachineStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new StringField('action_name', 'transitionActionName'))->setDescription('Unique name of transition action.'),
            (new FkField('admin_user_id', 'admin_userId', AdminUserDefinition::class))->setDescription('Unique identity of admin_user.'),
            new FkField('integration_id', 'integrationId', IntegrationDefinition::class),
            new LongTextField('internal_comment', 'internalComment'),
            (new StringField('source_type', 'sourceType', 32))->setDescription('Type of the API context source which triggered the transition.'),

            new ManyToOneAssociationField('admin_user', 'admin_user_id', AdminUserDefinition::class, 'id', false),
            new ManyToOneAssociationField('integration', 'integration_id', IntegrationDefinition::class, 'id', false),
        ]);
    }
}
