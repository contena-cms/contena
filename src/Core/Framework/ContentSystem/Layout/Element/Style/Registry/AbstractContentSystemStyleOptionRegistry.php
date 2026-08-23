<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Single authority over the universal style option set, read by both validation and introspection.
 */
abstract class AbstractContentSystemStyleOptionRegistry
{
    abstract public function getDecorated(): AbstractContentSystemStyleOptionRegistry;

    /**
     * Strict view for the write and install boundary: a cross-loader name collision throws.
     *
     * @return array<string, StyleOptionSpecification>
     */
    abstract public function all(): array;

    /**
     * Lenient view for the read paths: a cross-loader name collision is resolved by source precedence
     * and logged, so a duplicate never fails a read.
     *
     * @return array<string, StyleOptionSpecification>
     */
    abstract public function allResolved(): array;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
