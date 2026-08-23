<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

class AsArray extends Flag
{
    public function parse(): \Generator
    {
        yield 'as_array' => true;
    }
}
