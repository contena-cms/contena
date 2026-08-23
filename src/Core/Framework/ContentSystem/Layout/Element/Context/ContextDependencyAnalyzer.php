<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Context;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;

/**
 * Analyzes context dependencies to determine which ancestors must be kept during tree pruning.
 *
 * @internal
 *
 * @final
 */
class ContextDependencyAnalyzer
{
    /**
     * These elements need their ancestors processed first to receive context data.
     */
    public function requiresParentData(ContentElement $element): bool
    {
        return $element->getAcceptsContext() !== [];
    }

    /**
     * @param list<ContentElement> $pathElements Ordered from root to target
     *
     * @return int Index in path where context root is located (0 = root)
     */
    public function findDataRootIndex(array $pathElements): int
    {
        for ($i = \count($pathElements) - 1; $i >= 0; --$i) {
            if (!$this->requiresParentData($pathElements[$i])) {
                // Found element that doesn't need parent data - this is our context root
                return $i;
            }
        }

        // All elements require parent data, must keep from root
        return 0;
    }
}
