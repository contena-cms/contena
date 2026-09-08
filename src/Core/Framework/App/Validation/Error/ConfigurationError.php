<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class ConfigurationError implements Error
{
    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations, private readonly string $appName)
    {
        $this->message = \sprintf(
            "The following custom components are not allowed to be used in app configuration:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::INVALID_CONFIGURATION;
    }

    public function getParameters(): array
    {
        return ['appName' => $this->appName, 'error' => $this->message];
    }

    public function isBlocking(): bool
    {
        return true;
    }
}
