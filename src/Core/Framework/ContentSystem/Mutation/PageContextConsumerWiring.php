<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Contena\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;

/**
 * Wires page-context consumers into mutated content trees from diagnostic resolutions.
 *
 * @internal
 */
class PageContextConsumerWiring
{
    /**
     * @param list<ContentElement> $tree
     * @param array<string, list<PropertyResolution>> $resolutions per-element resolutions, keyed by element id
     * @param list<ProvidedContext> $rootContext the bound source's root-ambient context
     */
    public function apply(array $tree, array $resolutions, array $rootContext): void
    {
        foreach ($tree as $root) {
            $this->wire($root, [], $resolutions, $rootContext);
        }
    }

    /**
     * @param list<ContentElement> $ancestors the element's ancestors, root first
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param list<ProvidedContext> $rootContext
     */
    private function wire(ContentElement $element, array $ancestors, array $resolutions, array $rootContext): void
    {
        $consumed = $this->findConsumed($resolutions[$element->getId()] ?? [], $rootContext);

        if ($consumed !== null) {
            [$key, $type, $required] = $consumed;

            $this->addConsumer($element, $key, new ContextConsumer($type, $required));

            foreach ($ancestors as $ancestor) {
                $this->addConsumer($ancestor, $key, new ContextConsumer($type, false, true));
            }
        }

        $childAncestors = [...$ancestors, $element];

        foreach ($element->allSlotElements() as $child) {
            $this->wire($child, $childAncestors, $resolutions, $rootContext);
        }
    }

    /**
     * @param list<PropertyResolution> $resolutions
     * @param list<ProvidedContext> $rootContext
     *
     * @return array{0: string, 1: ContextType, 2: bool}|null the context key, its type and whether it is required
     */
    private function findConsumed(array $resolutions, array $rootContext): ?array
    {
        foreach ($resolutions as $resolution) {
            if ($resolution->kind !== PropertyKind::Reference) {
                continue;
            }

            $resolved = $resolution->resolved;

            if ($resolved !== null
                && $resolved->origin === CandidateOrigin::Parent
                && $resolved->contextKey === $resolution->key
                && $resolved->contextType !== null
            ) {
                return [$resolved->contextKey, $resolved->contextType, $resolution->required];
            }

            if ($resolved === null) {
                foreach ($rootContext as $context) {
                    if ($context->contextKey === $resolution->key && $context->fqcn === $resolution->fqcn) {
                        return [$context->contextKey, $context->contextType, $resolution->required];
                    }
                }
            }
        }

        return null;
    }

    private function addConsumer(ContentElement $element, string $key, ContextConsumer $consumer): void
    {
        $definitions = $element->getContextDefinitions();
        $consumers = $definitions->getAllConsumers();

        // Never override an existing consumer (authored, or already wired for another descendant).
        if (isset($consumers[$key])) {
            return;
        }

        $consumers[$key] = $consumer;

        $element->setContextDefinitions(new ContextDefinitions($definitions->getAllProviders(), $consumers));
    }
}
