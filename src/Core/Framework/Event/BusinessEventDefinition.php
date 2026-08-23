<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Struct\Struct;

class BusinessEventDefinition extends Struct
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $aware
     */
    public function __construct(protected string $name, protected string $class, protected array $data, protected array $aware = [])
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getAware(string $key): bool
    {
        return \in_array($key, $this->aware, true);
    }

    public function getApiAlias(): string
    {
        return 'business_event_definition';
    }
}
