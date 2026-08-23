<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class StateMachineTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'state_machine_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return StateMachineDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name')->addFlags(new Required()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
