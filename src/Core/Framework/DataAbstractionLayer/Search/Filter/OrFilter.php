<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class OrFilter extends MultiFilter
{
    /**
     * @param Filter[] $queries
     */
    public function __construct(array $queries = [])
    {
        parent::__construct(self::CONNECTION_OR, $queries);
    }
}
