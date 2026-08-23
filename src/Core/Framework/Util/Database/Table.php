<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Util\Database;

/**
 * @internal
 */
final readonly class Table
{
    /**
     * @param list<Column> $columns
     * @param list<Index> $indexes
     */
    public function __construct(
        public array $columns,
        public array $indexes,
    ) {
    }
}
