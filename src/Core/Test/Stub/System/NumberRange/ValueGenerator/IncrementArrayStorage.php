<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;

/**
 * @internal
 * Dummy increment storage which uses a local array.
 * Obviously only for usage in unit tests.
 */
class IncrementArrayStorage extends AbstractIncrementStorage
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $states;

    /**
     * @param list<IncrementState> $states
     */
    public function __construct(array $states)
    {
        $this->states = [];
        foreach ($states as $state) {
            $this->states[$state->dataScopeId][$state->numberRangeId] = $state->value;
        }
    }

    public function reserve(array $config, Context $context): int
    {
        $this->assertConfigurationMatchesContext($config, $context);

        $dataScopeId = $context->getDataScopeId();
        if (!isset($this->states[$dataScopeId][$config['id']])) {
            return $this->states[$dataScopeId][$config['id']] = 1;
        }

        return ++$this->states[$dataScopeId][$config['id']];
    }

    public function preview(array $config, Context $context): int
    {
        $this->assertConfigurationMatchesContext($config, $context);

        return ($this->states[$context->getDataScopeId()][$config['id']] ?? 0) + 1;
    }

    /**
     * @return array<string, IncrementState>
     */
    public function list(Context $context): array
    {
        $states = [];
        foreach ($this->states[$context->getDataScopeId()] ?? [] as $numberRangeId => $value) {
            $states[$numberRangeId] = new IncrementState($context->getDataScopeId(), $numberRangeId, $value);
        }

        return $states;
    }

    public function set(IncrementState $state, Context $context): void
    {
        $this->assertStateMatchesContext($state, $context);

        $this->states[$state->dataScopeId][$state->numberRangeId] = $state->value;
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
