<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Filter;

use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[BecomesInternal(version: 'v6.8.0')]
class CurrencyFilter extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly CurrencyFormatter $currencyFormatter)
    {
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters()
    {
        return [
            new TwigFilter('currency', $this->formatCurrency(...), ['needs_context' => true]),
        ];
    }

    /**
     * The arguments will be natively type-hinted in v6.8.0.
     *
     * @param array<string, mixed> $twigContext
     * @param float|null $price
     * @param string|null $currencyIsoCode
     * @param string|null $languageId
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return float|string
     */
    public function formatCurrency($twigContext, $price, $currencyIsoCode = null, $languageId = null, ?int $decimals = null)
    {
        if ($price === null) {
            $price = 0.0;
        }

        $context = TwigContextHelper::getContext($twigContext);
        if (!$context instanceof Context) {
            if (isset($twigContext['testMode']) && $twigContext['testMode'] === true) {
                return $price;
            }

            throw AdapterException::currencyFilterMissingContext();
        }

        if (!$currencyIsoCode) {
            $currencyIsoCode = TwigContextHelper::getChannelContext($twigContext)?->getCurrency()->getIsoCode();
        }

        if (!$currencyIsoCode) {
            if (isset($twigContext['testMode']) && $twigContext['testMode'] === true) {
                return $price;
            }

            throw AdapterException::currencyFilterMissingIsoCode();
        }

        if ($languageId === null) {
            $languageId = $context->getLanguageId();
        }

        return $this->currencyFormatter->formatCurrencyByLanguage($price, $currencyIsoCode, $languageId, $context, $decimals);
    }
}
