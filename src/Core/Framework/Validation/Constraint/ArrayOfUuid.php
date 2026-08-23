<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class ArrayOfUuid extends Constraint
{
    final public const string INVALID_MESSAGE = 'The value "{{ string }}" is not a valid uuid.';
    final public const string INVALID_TYPE_MESSAGE = 'This value should be of type array.';
    final public const string INVALID_TYPE_CODE = 'FRAMEWORK__INVALID_UUID_WRITE_CONSTRAINT_VALIDATION';
}
