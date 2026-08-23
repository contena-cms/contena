<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class TestDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = '_test_lock';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new StringField('description', 'description')->addFlags(new ApiAware()),
            new TranslatedField('name')->addFlags(new ApiAware()),
            new TranslationsAssociationField(TestTranslationDefinition::class, '_test_lock_id')->addFlags(new ApiAware()),
            new LockedField()->addFlags(new ApiAware()),
        ]);
    }
}
