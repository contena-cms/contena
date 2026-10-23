<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;


/**
 * Omits the field's current value when an entity is cloned, allowing its normal default to apply.
 */
class ResetOnClone extends Flag
{
    public function parse(): \Generator
    {
        yield 'reset_on_clone' => true;
    }
}
