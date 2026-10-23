<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Search;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
abstract class FilterStruct extends Struct
{
    protected string $type;

    /**
     * @return EqualsFilterStruct|MultiFilterStruct
     */
    public static function fromArray(array $data): FilterStruct
    {
        $type = $data['type'];

        if ($type === 'multi') {
            return MultiFilterStruct::fromArray($data);
        }

        if ($type === 'equals') {
            return EqualsFilterStruct::fromArray($data);
        }

        throw new \InvalidArgumentException('Type ' . $type . ' not allowed');
    }

    /**
     * @return array<string, string>
     */
    abstract public function getQueryParameter(): array;

    public function getType(): string
    {
        return $this->type;
    }
}
