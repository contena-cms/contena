<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class ContentSystemElementTypeSchemaError implements Error
{
    private const KEY = 'manifest-invalid-element-type-schema';

    private readonly string $message;

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid element type schema in "%s": %s',
            $filename,
            $reason
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
        return AppException::CONTENT_SYSTEM_ELEMENT_TYPE_LOAD_FAILED;
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
