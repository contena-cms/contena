<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool\Stub;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Composes the trait the way a tool does and opens its private entry points to the test, so the test class
 * itself stays free of production traits.
 *
 * @internal
 */
final class McpEntityIncludesStub
{
    use McpEntityIncludes;

    public function launchApplyDefaultIncludes(EntityDefinition $definition, Criteria $criteria): void
    {
        $this->applyDefaultIncludes($definition, $criteria);
    }

    /**
     * @return array<string, list<string>>
     */
    public function launchBuildDefaultIncludes(EntityDefinition $definition, Criteria $criteria): array
    {
        return $this->buildDefaultIncludes($definition, $criteria);
    }
}
