<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<InvalidPluralizationStruct>
 *
 * @codeCoverageIgnore
 */
class InvalidPluralizationCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return InvalidPluralizationStruct::class;
    }
}
