<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\Log\Package;

class CurrencyExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (\preg_match('/SQLSTATE\\[23000\\]:.*1062 Duplicate entry \'(?<isoCode>.*)\' for key \'(?:currency\\.)?uniq\\.currency\\.iso_code\'/', $e->getMessage(), $matches)) {
            return CurrencyException::isoCodeNotUnique($matches['isoCode'], $e);
        }

        return null;
    }
}
