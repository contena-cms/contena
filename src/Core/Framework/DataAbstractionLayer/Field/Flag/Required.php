<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

use Contena\Core\Framework\Log\Package;

class Required extends Flag
{
    public function parse(): \Generator
    {
        yield 'required' => true;
    }
}
