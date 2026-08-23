<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Api;

use Contena\Core\Framework\Struct\Struct;

class FlowActionDefinition extends Struct
{
    /**
     * @param list<string> $requirements
     */
    public function __construct(protected string $name, protected array $requirements, protected bool $delayable = false)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getDelayable(): bool
    {
        return $this->delayable;
    }
}
