<?php declare(strict_types=1);

namespace Contena\Core\Framework\Struct;

use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Log\Package;

trait CreateFromTrait
{
    public static function createFrom(Struct $object): static
    {
        try {
            $self = (new \ReflectionClass(static::class))
                ->newInstanceWithoutConstructor();
        } catch (\ReflectionException $exception) {
            throw FrameworkException::createFromError($exception->getMessage());
        }

        foreach (get_object_vars($object) as $property => $value) {
            // @phpstan-ignore property.dynamicName (We have to allow dynamic properties here to copy all variables)
            $self->$property = $value;
        }

        return $self;
    }
}
