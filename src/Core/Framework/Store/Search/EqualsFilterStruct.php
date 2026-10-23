<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Search;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class EqualsFilterStruct extends FilterStruct
{
    protected string $field;

    protected string $value;

    /**
     * @param array<string, string> $data
     */
    public static function fromArray(array $data): FilterStruct
    {
        $filter = new EqualsFilterStruct();
        $filter->assign($data);

        return $filter;
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParameter(): array
    {
        return [$this->field => $this->value];
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
