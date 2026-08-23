<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class ArrayOfType extends Constraint
{
    final public const string INVALID_MESSAGE = 'This value "{{ value }}" should be of type {{ type }}.';
    final public const string INVALID_TYPE_MESSAGE = 'This value should be of type array.';

    public function __construct(public string $type)
    {
        parent::__construct();
    }
}
