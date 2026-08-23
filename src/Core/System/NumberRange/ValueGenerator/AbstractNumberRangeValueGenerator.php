<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;

abstract class AbstractNumberRangeValueGenerator
{
    /**
     * Generates a new value while taking care of states, events and connectors.
     */
    abstract public function getValue(string $type, Context $context, bool $preview = false): string;

    /**
     * Generates a preview for a persisted number range without mutating its state.
     */
    abstract public function previewPatternByNumberRangeId(string $numberRangeId, ?string $pattern = null, ?int $start = null): string;

    abstract protected function getDecorated(): self;
}
