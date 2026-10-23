<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 *
 * @codeCoverageIgnore
 */
class ImportExportProfileTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = ImportExportProfileDefinition::ENTITY_NAME . '_translation';

    // @phpstan-ignore contena.internalMethod (Preserve the inherited public constructor contract.)
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportProfileTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.2.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ImportExportProfileDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('label', 'label'),
        ]);
    }
}
