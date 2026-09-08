<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class IncompatibleAppError implements Error
{
    private readonly string $message;

    public function __construct(private readonly string $appName)
    {
        $this->message = \sprintf('App %s is not compatible with this Contena version', $appName);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::NOT_COMPATIBLE;
    }

    public function getParameters(): array
    {
        return ['name' => $this->appName];
    }

    public function isBlocking(): bool
    {
        return true;
    }
}
