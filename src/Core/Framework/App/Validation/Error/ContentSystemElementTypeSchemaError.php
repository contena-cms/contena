<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

/**
 * @internal only for use by the app-system
 */
class ContentSystemElementTypeSchemaError extends Error
{
    private const KEY = 'manifest-invalid-element-type-schema';

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid element type schema in "%s": %s',
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
