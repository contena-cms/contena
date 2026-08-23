<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TypedDefault extends Constraint
{
    public string $nonPrimitiveMessage = 'default is only valid when exactly one primitive type is declared (string, integer, boolean, number)';

    public string $typeMessage = 'default value must match the declared type "{{ type }}"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
