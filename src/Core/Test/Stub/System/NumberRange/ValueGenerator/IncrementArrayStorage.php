<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * @internal
 * Dummy increment storage which uses a local array.
 * Obviously only for usage in unit tests.
 */
class IncrementArrayStorage extends AbstractIncrementStorage
{
    /**
     * @param array<string, int> $states
     */
    public function __construct(private array $states)
    {
    }

    public function reserve(array $config): int
    {
        if (!isset($this->states[$config['id']])) {
            return $this->states[$config['id']] = 1;
        }

        return ++$this->states[$config['id']];
    }

    public function preview(array $config): int
    {
        return ($this->states[$config['id']] ?? 0) + 1;
    }

    /**
     * @return array<string, int>
     */
    public function list(): array
    {
        return $this->states;
    }

    public function set(string $configurationId, int $value): void
    {
        $this->states[$configurationId] = $value;
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        throw new DecorationPatternException(self::class);
    }
}
