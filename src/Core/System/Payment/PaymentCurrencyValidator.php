<?php declare(strict_types=1);

namespace Contena\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Currency\CurrencyCollection;

/**
 * @internal
 */
final readonly class PaymentCurrencyValidator
{
    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(private EntityRepository $currencyRepository)
    {
    }

    public function validate(string $currencyCode, Context $context): string
    {
        $currencyCode = strtoupper($currencyCode);
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('isoCode', $currencyCode))
            ->setLimit(1);

        if ($this->currencyRepository->searchIds($criteria, $context)->firstId() === null) {
            throw PaymentException::currencyNotSupported($currencyCode);
        }

        return $currencyCode;
    }
}
