<?php declare(strict_types=1);

namespace Contena\Core\Test\Field;

use Contena\SomewhereElse\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class NotInCoreNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
