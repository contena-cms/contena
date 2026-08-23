<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Prevents FkFieldSerializer from auto-filling required FK values from the WriteContext.
 */
class DoNotUseContext extends Flag
{
    public function parse(): \Generator
    {
        yield 'do_not_use_context' => true;
    }
}
