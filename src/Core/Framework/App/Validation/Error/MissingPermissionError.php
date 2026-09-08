<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class MissingPermissionError implements Error
{
    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "The following permissions are missing:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function isBlocking(): bool
    {
        return true;
    }
}
