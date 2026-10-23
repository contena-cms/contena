<?php declare(strict_types=1);

namespace Contena\Core\System\TaxProvider\Aggregate\TaxProviderTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxProviderTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class TaxProviderTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_provider_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxProviderTranslationEntity::class;
    }
}
