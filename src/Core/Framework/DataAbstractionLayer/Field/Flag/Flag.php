<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class Flag
{
    /**
     * Returns a readable name for the flag
     *
     * @return \Generator<string, bool|string|float|list<list<string>>|array<string, string|null>|array{choices: list<string|bool|int|float>, strict: bool}>
     */
    abstract public function parse(): \Generator;
}
