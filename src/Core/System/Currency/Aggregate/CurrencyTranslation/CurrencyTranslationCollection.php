<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Aggregate\CurrencyTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CurrencyTranslationEntity>
 */
class CurrencyTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationEntity::class;
    }
}
