<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;

#[BecomesInternal(version: 'v6.8.0')]
class NamespaceHierarchyBuilder
{
    /**
     * @internal
     *
     * @param iterable<TemplateNamespaceHierarchyBuilderInterface> $namespaceHierarchyBuilders
     */
    public function __construct(private readonly iterable $namespaceHierarchyBuilders)
    {
    }

    /**
     * @return array<string, int>
     */
    public function buildHierarchy(): array
    {
        $hierarchy = [];

        foreach ($this->namespaceHierarchyBuilders as $hierarchyBuilder) {
            $hierarchy = $hierarchyBuilder->buildNamespaceHierarchy($hierarchy);
        }

        return $hierarchy;
    }
}
