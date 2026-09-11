<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Registry;

use Contena\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
abstract class AbstractContentSystemLayoutPresetRegistry
{
    abstract public function getDecorated(): AbstractContentSystemLayoutPresetRegistry;

    /**
     * @return array<string, ContentSystemLayoutPresetSpecification>
     */
    abstract public function all(): array;

    abstract public function has(string $id): bool;

    abstract public function get(string $id): ContentSystemLayoutPresetSpecification;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
