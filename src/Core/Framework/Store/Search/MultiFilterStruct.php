<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Search;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class MultiFilterStruct extends FilterStruct
{
    /**
     * @var FilterStruct[]
     */
    protected array $queries;

    protected string $operator;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): FilterStruct
    {
        $queries = $data['queries'];

        $data['queries'] = array_map(static fn (array $query): FilterStruct => FilterStruct::fromArray($query), $queries);

        $filter = new MultiFilterStruct();
        $filter->assign($data);

        return $filter;
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParameter(): array
    {
        $parameter = [];

        foreach ($this->getQueries() as $query) {
            $parameter = array_merge($parameter, $query->getQueryParameter());
        }

        return $parameter;
    }

    /**
     * @return array<FilterStruct>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
