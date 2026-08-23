<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader;

use Contena\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Contena\Core\Framework\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;

/**
 * Canonicalizes an encoded data-loader config array into a stable shape for structural comparison: key-sorts
 * every map level and value-sorts every list level (e.g. an `associations` list), so two configs that differ
 * only in key or list order compare equal.
 *
 * Shared by {@see PropertiesExtractionVisitor} (dedup hash) and {@see AttributionReconciler} (honesty check).
 *
 * @internal
 *
 * @final
 */
class ConfigCanonicalizer
{
    /**
     * @param array<int|string, mixed> $config
     *
     * @return array<int|string, mixed>
     */
    public function canonicalize(array $config): array
    {
        if (array_is_list($config)) {
            return $this->canonicalizeList($config);
        }

        ksort($config);

        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                $config[$key] = $this->canonicalize($value);
            }
        }

        return $config;
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     */
    private function canonicalizeList(array $list): array
    {
        foreach ($list as $index => $item) {
            if (\is_array($item)) {
                $list[$index] = $this->canonicalize($item);
            }
        }

        sort($list);

        return $list;
    }
}
