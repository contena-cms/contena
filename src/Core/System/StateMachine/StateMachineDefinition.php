<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;

class StateMachineDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'state_machine';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of state machine.'),

            new StringField('technical_name', 'technicalName')->addFlags(new Required())->setDescription('Technical name of state machine.'),
            new TranslatedField('name')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('customFields'),

            new OneToManyAssociationField('states', StateMachineStateDefinition::class, 'state_machine_id')->addFlags(new ApiAware(), new CascadeDelete()),
            new OneToManyAssociationField('transitions', StateMachineTransitionDefinition::class, 'state_machine_id')->addFlags(new ApiAware(), new CascadeDelete()),
            new FkField('initial_state_id', 'initialStateId', StateMachineStateDefinition::class),

            new TranslationsAssociationField(StateMachineTranslationDefinition::class, 'state_machine_id')->addFlags(new CascadeDelete(), new Required()),
            new OneToManyAssociationField('historyEntries', StateMachineHistoryDefinition::class, 'state_machine_id'),
        ]);
    }
}
