<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<InvalidPluralizationStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class InvalidPluralizationCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return InvalidPluralizationStruct::class;
    }
}
