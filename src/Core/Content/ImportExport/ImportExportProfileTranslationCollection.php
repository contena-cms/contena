<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 *
 * @extends EntityCollection<ImportExportProfileTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class ImportExportProfileTranslationCollection extends EntityCollection
{
    /**
     * @param iterable<ImportExportProfileTranslationEntity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct($elements);
    }

    protected function getExpectedClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }
}
