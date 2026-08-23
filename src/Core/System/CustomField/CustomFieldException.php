<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CustomFieldException extends HttpException
{
    public const string CUSTOM_FIELD_NAME_INVALID = 'CUSTOM_FIELD_NAME_INVALID';

    public static function customFieldNameInvalid(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOM_FIELD_NAME_INVALID,
            'Invalid field name: Only letters, numbers, or underscores are allowed, and it must start with a letter or underscore.',
            ['field' => 'name', 'value' => $name]
        );
    }
}
