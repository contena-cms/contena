<?php

namespace PHPSTORM_META {
    expectedArguments(
        \Contena\Core\Framework\DataAbstractionLayer\Search\Criteria::setTotalCountMode(),
        0,
        \Contena\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NONE,
        \Contena\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_EXACT,
        \Contena\Core\Framework\DataAbstractionLayer\Search\Criteria::TOTAL_COUNT_MODE_NEXT_PAGES
    );

    expectedArguments(
        \Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::__construct(),
        1,
        \Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::ASCENDING,
        \Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting::DESCENDING
    );

}
