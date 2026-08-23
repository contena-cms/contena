<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * @codeCoverageIgnore
 *
 * @description This flag indicates that the field is write-once and then read-only
 */
class Immutable extends Flag
{
    public function parse(): \Generator
    {
        yield 'immutable' => true;
    }
}
