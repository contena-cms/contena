<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

/**
 * Aggregates every binding-specification violation of one manifest into a single error, because
 * {@see ErrorCollection} keys errors by their message key; a second error of the same class would
 * silently replace the first (same convention as {@see MissingPermissionError}).
 *
 * @internal only for use by the app-system
 */
class ContentSystemBindingSpecificationSchemaError extends Error
{
    private const KEY = 'manifest-invalid-binding-specification-schema';

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "The following content-system binding specifications are invalid:\n- %s",
            implode("\n- ", $violations)
        );

        parent::__construct($this->message);
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }
}
