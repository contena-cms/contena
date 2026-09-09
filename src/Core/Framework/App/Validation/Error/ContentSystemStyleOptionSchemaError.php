<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\App\AppException;

/**
 * @internal only for use by the app-system
 */
class ContentSystemStyleOptionSchemaError implements Error
{
    private const KEY = 'manifest-invalid-style-option-schema';

    private readonly string $message;

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid style option schema in "%s": %s',
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
        return AppException::CONTENT_SYSTEM_STYLE_OPTION_LOAD_FAILED;
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
