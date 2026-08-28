<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;

/**
 * @internal
 *
 * @final
 */
class MutationPipeline
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
        private readonly PageContextConsumerWiring $contextWiring,
    ) {
    }

    /**
     * @param list<ContentElement> $tree the decoded draft tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function run(LayoutMutation $mutation, array $tree, ?array $rootContext): MutationResult
    {
        $mutated = $mutation->apply($tree);
        $affected = $mutation->affected();

        $analysis = $this->diagnostics->analyze($mutated, $rootContext);

        // Wire page-context consumers into the mutated tree so the returned layout carries the
        // distribution wiring required by every consumer.
        $this->contextWiring->apply($mutated, $analysis->resolutions, $rootContext ?? []);

        // This MutationResult assembly is intentionally duplicated in PersistedLayoutMutator::mutate(): sharing it
        // would couple Mutation/ to a Diagnostics/LayoutAnalysis-shaped helper or require a banned static helper,
        // so each runner assembles its own result from its own analysis.
        return new MutationResult(
            $mutated,
            array_intersect_key($analysis->resolutions, array_flip($affected)),
            $analysis->report,
            $affected,
            $mutation->orphaned(),
            $mutation->droppedWiring(),
            $mutation->droppedProperties(),
        );
    }
}
