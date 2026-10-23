<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as the exception handler is no longer needed, languages now also throw RestrictDeleteViolationException
 * @see RestrictDeleteViolationException is now thrown instead
 */
class LanguageExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_LATE;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        return null;
    }
}
