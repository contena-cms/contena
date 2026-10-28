<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

use Contena\Core\Framework\Log\Package;

/**
 * Defines that the data of this field is stored in an Entity::$extension and are not part of the struct itself.
 */
#[Package('framework')]
class Extension extends Flag
{
    public function parse(): \Generator
    {
        yield 'extension' => true;
    }
}
