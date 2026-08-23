<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class WriteProtectedTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = '_test_nullable_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return WriteProtectedTranslatedDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('protected', 'protected')->addFlags(new ApiAware()),
            new StringField('system_protected', 'systemProtected')->addFlags(new ApiAware()),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
