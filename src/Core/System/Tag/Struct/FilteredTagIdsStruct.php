<?php declare(strict_types=1);

namespace Contena\Core\System\Tag\Struct;

use Contena\Core\Framework\Struct\Struct;

class FilteredTagIdsStruct extends Struct
{
    /**
     * @param array<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected int $total
    ) {
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
