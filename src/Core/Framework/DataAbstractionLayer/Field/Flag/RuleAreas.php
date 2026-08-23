<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

class RuleAreas extends Flag
{
    final public const string FLOW_AREA = 'flow';
    final public const string FLOW_CONDITION_AREA = 'flow-condition';

    /**
     * @var string[]
     */
    private readonly array $areas;

    public function __construct(string ...$areas)
    {
        $this->areas = $areas;
    }

    public function parse(): \Generator
    {
        yield 'rule_areas' => true;
    }

    /**
     * @return string[]
     */
    public function getAreas(): array
    {
        return $this->areas;
    }
}
