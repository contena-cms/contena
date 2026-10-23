<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Contena\Core\Framework\Log\Package;

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
