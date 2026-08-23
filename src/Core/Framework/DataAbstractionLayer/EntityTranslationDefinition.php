<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\System\Language\LanguageDefinition;

abstract class EntityTranslationDefinition extends EntityDefinition
{
    public function getParentDefinition(): EntityDefinition
    {
        $parentDefinition = parent::getParentDefinition();
        if ($parentDefinition === null) {
            throw new \RuntimeException('Translation entity definitions always need a parent definition');
        }

        return $parentDefinition;
    }

    public function isVersionAware(): bool
    {
        return $this->getParentDefinition()->isVersionAware();
    }

    public function hasRequiredField(): bool
    {
        return $this->getFields()
                ->filterByFlag(Required::class)
                ->filter(static function (Field $field) {
                    return !(
                        $field instanceof FkField
                        || $field instanceof CreatedAtField
                        || $field instanceof UpdatedAtField
                    );
                })
                ->count()
            > 0;
    }

    protected function getParentDefinitionClass(): string
    {
        throw new \RuntimeException('`getParentDefinitionClass` not implemented');
    }

    protected function getBaseFields(): array
    {
        $translatedDefinition = $this->getParentDefinition();
        $entityName = $translatedDefinition->getEntityName();

        $propertyBaseName = explode('_', $entityName);
        $propertyBaseName = array_map('ucfirst', $propertyBaseName);
        $propertyBaseName = lcfirst(implode('', $propertyBaseName));

        $baseFields = [
            new FkField($entityName . '_id', $propertyBaseName . 'Id', $translatedDefinition->getEntityName(), 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new FkField('language_id', 'languageId', LanguageDefinition::ENTITY_NAME, 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new ManyToOneAssociationField($propertyBaseName, $entityName . '_id', $translatedDefinition->getEntityName(), 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::ENTITY_NAME, 'id', false),
        ];

        if ($this->isVersionAware()) {
            $baseFields[] = new ReferenceVersionField($translatedDefinition->getClass())->addFlags(new PrimaryKey(), new Required());
        }

        return $baseFields;
    }
}
