<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

/**
 * @internal
 */
interface CriteriaPartInterface
{
    /**
     * @return list<string>
     */
    public function getFields(): array;
}
