<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Contena\Core\Framework\Struct\Struct;

class FieldSorting extends Struct implements CriteriaPartInterface
{
    public const string ASCENDING = 'ASC';
    public const string DESCENDING = 'DESC';

    public function __construct(
        protected string $field,
        protected string $direction = self::ASCENDING,
        protected bool $naturalSorting = false
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getFields(): array
    {
        return [$this->field];
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getNaturalSorting(): bool
    {
        return $this->naturalSorting;
    }

    public function getApiAlias(): string
    {
        return 'dal_field_sorting';
    }
}
