<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Pricing;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PriceRuleEntity>
 *
 * @codeCoverageIgnore
 */
class PriceRuleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_price_rule_collection';
    }

    protected function getExpectedClass(): string
    {
        return PriceRuleEntity::class;
    }
}
