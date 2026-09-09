<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * Aggregates every binding-specification violation of one manifest into a single error, because
 * {@see ErrorCollection} keys errors by their message key; a second error of the same class would
 * silently replace the first (same convention as {@see MissingPermissionError}).
 *
 * @internal only for use by the app-system
 */
class ContentSystemBindingSpecificationSchemaError implements Error
{
    private const KEY = 'manifest-invalid-binding-specification-schema';

    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "The following content-system binding specifications are invalid:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::CONTENT_SYSTEM_BINDING_SPECIFICATION_LOAD_FAILED;
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
