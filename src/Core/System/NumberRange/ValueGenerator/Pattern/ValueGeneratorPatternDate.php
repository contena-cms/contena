<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class ValueGeneratorPatternDate extends AbstractValueGenerator
{
    final public const string STANDARD_FORMAT = 'Y-m-d';

    public function getPatternId(): string
    {
        return 'date';
    }

    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        if ($args === null || $args === []) {
            $args[] = self::STANDARD_FORMAT;
        }

        return date($args[0]);
    }

    public function getDecorated(): AbstractValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }
}
