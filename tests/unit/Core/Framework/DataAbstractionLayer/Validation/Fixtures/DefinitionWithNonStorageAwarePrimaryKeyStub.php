<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * Test fixture with a non-StorageAware field incorrectly marked as PrimaryKey.
 * Used to test that the validator skips non-StorageAware fields when checking primary keys.
 */
class DefinitionWithNonStorageAwarePrimaryKeyStub extends DefinitionStub
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        // Add a TranslatedField (which is not StorageAware) and mark it as PrimaryKey
        // This is an unusual/incorrect configuration, but we need to test the handling
        $translatedField = new TranslatedField('translated');
        $translatedField->addFlags(new PrimaryKey());
        $fields->add($translatedField);

        return $fields;
    }
}
