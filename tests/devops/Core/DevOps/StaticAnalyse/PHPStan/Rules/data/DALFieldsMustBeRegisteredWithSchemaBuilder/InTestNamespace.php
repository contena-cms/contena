<?php declare(strict_types=1);

namespace Contena\Core\Test\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @internal
 */
class InTestNamespace extends Field
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
