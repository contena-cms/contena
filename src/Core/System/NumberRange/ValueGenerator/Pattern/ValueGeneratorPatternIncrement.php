<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

class ValueGeneratorPatternIncrement extends AbstractValueGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractIncrementStorage $incrementConnector)
    {
    }

    public function getPatternId(): string
    {
        return 'n';
    }

    /**
     * @param array<int, string> $args
     */
    public function generate(array $config, Context $context, ?array $args = null, bool $preview = false): string
    {
        if ($preview) {
            return (string) $this->incrementConnector->preview($config, $context);
        }

        return (string) $this->incrementConnector->reserve($config, $context);
    }

    public function getDecorated(): AbstractValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }
}
