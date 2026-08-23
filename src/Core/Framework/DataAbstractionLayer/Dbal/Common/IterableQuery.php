<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;

/**
 * @internal
 */
interface IterableQuery
{
    /**
     * @return array<string|int, mixed>
     */
    public function fetch(): array;

    public function fetchCount(): int;

    public function getQuery(): QueryBuilder;

    /**
     * @return array{offset: int|null}
     */
    public function getOffset(): array;
}
