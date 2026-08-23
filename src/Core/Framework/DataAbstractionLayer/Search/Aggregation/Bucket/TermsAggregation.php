<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @final
 */
class TermsAggregation extends BucketAggregation
{
    public function __construct(
        string $name,
        string $field,
        protected readonly ?int $limit = null,
        protected ?FieldSorting $sorting = null,
        ?Aggregation $aggregation = null
    ) {
        parent::__construct($name, $field, $aggregation);
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getSorting(): ?FieldSorting
    {
        return $this->sorting;
    }

    public function getFields(): array
    {
        $fields = parent::getFields();

        if (!$this->sorting) {
            return $fields;
        }
        foreach ($this->sorting->getFields() as $field) {
            $fields[] = $field;
        }

        return $fields;
    }
}
