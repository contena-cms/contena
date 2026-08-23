<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class NandFilter extends NotFilter
{
    /**
     * @param Filter[] $queries
     */
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_AND, $queries);
    }
}
