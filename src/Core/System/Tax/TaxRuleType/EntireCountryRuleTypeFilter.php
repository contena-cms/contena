<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\TaxRuleType;

use Contena\Core\Framework\Log\Package;

#[Package('checkout')]
class EntireCountryRuleTypeFilter extends AbstractTaxRuleTypeFilter
{
    final public const TECHNICAL_NAME = 'entire_country';


}
