<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use AbstractContentStreamBuilder instead
 */
interface ContentStreamBuilderInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed, use AbstractContentStreamBuilder::enrichCriteria instead
     *
     * @return array<int, Filter>
     */
    public function buildFilters(
        string $id,
        Context $context
    ): array;
}
