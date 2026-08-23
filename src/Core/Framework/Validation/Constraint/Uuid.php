<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class Uuid extends Constraint
{
    final public const string INVALID_MESSAGE = 'The string "{{ string }}" is not a valid uuid.';
    final public const string INVALID_TYPE_MESSAGE = 'This value should be of type string.';
}
