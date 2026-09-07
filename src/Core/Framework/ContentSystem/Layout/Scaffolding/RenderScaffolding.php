<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Scaffolding;

/**
 * The structural facts the tree preparation stage establishes and the finishing stage consumes,
 * so the finishing stage never re-derives them from a tree the preparation already changed.
 *
 * `$virtualRootSurvivedPrune` is a runtime outcome rather than a restatement of the wrap decision:
 * a partial render addressed at an element that needs no page-level context prunes the virtual root
 * away, and the finishing stage must then not unwrap.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class RenderScaffolding
{
    public function __construct(
        public bool $virtualRootSurvivedPrune,
        public ?string $extractTargetId,
    ) {
    }
}
