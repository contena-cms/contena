<?php
declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern;

/**
 * @phpstan-import-type ValueGeneratorConfig from AbstractValueGenerator
 */
class ValueGeneratorPatternRegistry
{
    /**
     * @var AbstractValueGenerator[]
     */
    private array $pattern = [];

    /**
     * @internal
     *
     * @param AbstractValueGenerator[] $patterns
     */
    public function __construct(iterable $patterns)
    {
        /** @var AbstractValueGenerator $pattern */
        foreach ($patterns as $pattern) {
            $this->pattern[$pattern->getPatternId()] = $pattern;
        }
    }

    /**
     * @param ValueGeneratorConfig $config
     * @param array<int, string>|null $args
     */
    public function generatePattern(string $pattern, string $patternPart, array $config, ?array $args = null, ?bool $preview = false): string
    {
        $generator = $this->pattern[$pattern] ?? null;

        if (!$generator) {
            return $patternPart;
        }

        return $generator->generate($config, $args, $preview);
    }
}
