<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * @internal only for use by the content-system element types
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TranslatableType extends Constraint
{
    public string $message = 'translatable is only valid with type "string"';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
