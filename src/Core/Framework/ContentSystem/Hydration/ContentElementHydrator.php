<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
class ContentElementHydrator
{
    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
        private readonly DataContextResolver $contextResolver,
    ) {
    }

    /**
     * Hydrates elements in two phases:
     *
     * Phase 1 (data loading): For each element, executes its data requirements depth-first.
     * Each loader result is stored in the element's properties under the requirement key:
     *   $element->setProperty($key, $result->data)
     * This is where FQCN-typed properties (declared in the element type spec but absent
     * from storage) materialize in the element's property map.
     *
     * Phase 2 (context resolution): After ALL elements are loaded, distributes provider
     * data to consumer descendants via setProperty(). Phase 2 must follow Phase 1 because
     * providers may expose loaded data as context.
     *
     * After both phases, the element's properties map contains static values, loaded data,
     * and context-provided data — indistinguishable from each other.
     *
     * @param iterable<ContentElement> $elements
     *
     * @return \Generator<ContentElement>
     */
    public function hydrate(
        iterable $elements,
        ChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): \Generator {
        $loadedElements = [];
        foreach ($elements as $element) {
            $this->hydrateElement($element, $context, $request, $cacheContext);
            $loadedElements[] = $element;
        }

        foreach ($loadedElements as $element) {
            $this->contextResolver->resolve($element);
            yield $element;
        }
    }

    private function hydrateElement(
        ContentElement $element,
        ChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): void {
        if ($element->requiresData()) {
            $dataRequirements = $element->getDataRequirements();

            foreach ($dataRequirements as $key => $requirement) {
                $loader = $this->dataLoaderProvider->get($requirement->source);
                $result = $loader->load($element, $requirement, $context, $request);

                if ($result->hasData()) {
                    $element->setProperty($key, $result->data);
                }

                $this->processCacheTags($result, $cacheContext);
            }
        }

        foreach ($element->allSlotElements() as $child) {
            $this->hydrateElement($child, $context, $request, $cacheContext);
        }
    }

    private function processCacheTags(ContentDataLoaderResult $result, RenderingCacheContext $cacheContext): void
    {
        if (!$result->isCacheAware()) {
            $cacheContext->disable();

            return;
        }

        $cacheContext->addTags($result->getCacheTags());
    }
}
