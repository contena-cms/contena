<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 */
class ContentSystemStyleOptionSchemaError extends Error
{
    private const KEY = 'manifest-invalid-style-option-schema';

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid style option schema in "%s": %s',
            $filename,
            $reason
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
