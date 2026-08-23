<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineState;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Contena\Core\System\StateMachine\StateMachineDefinition;

class StateMachineStateDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'state_machine_state';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineStateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineStateCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of state machine state.'),

            new StringField('technical_name', 'technicalName')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Technical name of StateMachineState.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class)->addFlags(new Required())->setDescription('Unique identity of StateMachine.'),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, 'id', false),
            new OneToManyAssociationField('fromStateMachineTransitions', StateMachineTransitionDefinition::class, 'from_state_id'),
            new OneToManyAssociationField('toStateMachineTransitions', StateMachineTransitionDefinition::class, 'to_state_id'),

            new TranslationsAssociationField(StateMachineStateTranslationDefinition::class, 'state_machine_state_id')->addFlags(new Required(), new CascadeDelete()),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new OneToManyAssociationField('toStateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'to_state_id'),
            new OneToManyAssociationField('fromStateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'from_state_id'),
        ]);
    }
}
