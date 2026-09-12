<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Contena\Core\Framework\Context;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;

/**
 * @phpstan-import-type ValueGeneratorConfig from AbstractValueGenerator
 */
abstract class AbstractIncrementStorage
{
    /**
     * Reserves and fetches the next increment atomically
     *
     * @param ValueGeneratorConfig $config
     */
    abstract public function reserve(array $config, Context $context): int;

    /**
     * Fetches the next increment value without reserving it
     *
     * @param ValueGeneratorConfig $config
     */
    abstract public function preview(array $config, Context $context): int;

    /**
     * Lists the current increment states, indexed by the number range configuration id
     *
     * This operation always lists the Context's exact write scope, even when
     * that Context also permits cross-scope reads.
     *
     * @return array<string, IncrementState>
     */
    abstract public function list(Context $context): array;

    /**
     * Sets the current increment state to the given value for the given number range configuration.
     * Mainly used for migrating between different increment storages.
     * Note: Calling this method and overwriting the current increment state may lead to duplicated increments!
     */
    abstract public function set(IncrementState $state, Context $context): void;

    abstract public function getDecorated(): self;

    /**
     * @param ValueGeneratorConfig $config
     */
    final protected function assertConfigurationMatchesContext(array $config, Context $context): void
    {
        if ($config['dataScopeId'] !== $context->getDataScopeId()) {
            throw new \InvalidArgumentException('Number range configuration does not belong to the current data scope.');
        }
    }

    final protected function assertStateMatchesContext(IncrementState $state, Context $context): void
    {
        if ($state->dataScopeId !== $context->getDataScopeId()) {
            throw new \InvalidArgumentException('Increment state does not belong to the current data scope.');
        }
    }
}
