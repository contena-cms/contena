<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;

use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class NotEqualsAnyFilter extends NotFilter
{
    /**
     * @param list<string>|array<string, string>|list<float>|list<int> $value
     */
    public function __construct(
        protected readonly string $field,
        protected array $value = []
    ) {
        parent::__construct(self::CONNECTION_AND, [
            new EqualsAnyFilter($field, $value),
        ]);
    }
}
