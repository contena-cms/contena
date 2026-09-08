<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
class ActionButtonTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'app_action_button_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ActionButtonTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ActionButtonDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('label', 'label')->addFlags(new Required()),
        ]);
    }
}
