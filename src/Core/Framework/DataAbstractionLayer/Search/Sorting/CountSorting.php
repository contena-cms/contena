<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Sorting;

/**
 * @final
 */
class CountSorting extends FieldSorting
{
    protected string $type = 'count';

    public function getApiAlias(): string
    {
        return 'dal_count_sorting';
    }
}
