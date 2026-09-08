<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class AppNameError implements Error
{
    private readonly string $message;

    public function __construct(string $appName)
    {
        $this->message = \sprintf(
            'The technical app name "%s" in the "manifest.xml" and the folder name must be equal.',
            $appName
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
        return false;
    }
}
