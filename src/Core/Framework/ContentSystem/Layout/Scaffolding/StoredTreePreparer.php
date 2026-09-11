<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Scaffolding;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Output\PartialRenderer;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Brings a stored forest into the state the rendering steps require, and hands stored forests back. Its
 * steps are ordered and internal: a caller asks for a prepared tree, never for one of the steps.
 *
 * The order is language reduction, then placeholder resolution, then the virtual-root wrap, then the partial
 * prune, and finally the scaffolding the finishing steps read. The first two run in FULL mode only. The
 * skeleton response carries a tree's structure and its style, never a property value, so collapsing and
 * resolving into values it discards is work no reader can observe.
 *
 * Language reduction is a value-level collapse inside preparation, which is why it is not named a lowering:
 * that role name belongs to the stored-to-rendered model translation. The two passes are distinct.
 *
 * @internal
 */
final class StoredTreePreparer
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
    ) {
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function prepare(
        array $tree,
        RenderingSpecification $specification,
        RenderingMode $mode,
        ChannelContext $channelContext,
    ): TreePreparationResult {
        if ($mode === RenderingMode::FULL) {
            $tree = $this->reduceTreeLanguage($tree, $channelContext);
            $tree = $this->resolveTreePlaceholders($tree, $specification, $mode);
        }

        $virtualRootWrapped = $this->virtualRootWrapper->requiresWrapping($specification, $tree);

        if ($virtualRootWrapped) {
            $tree = [$this->virtualRootWrapper->wrap($tree, $specification)];
        }

        $prePruneForest = $tree;

        $extractTargetId = $this->extractTargetId($specification);
        $tree = $this->pruneToTarget($tree, $extractTargetId);

        return new TreePreparationResult(
            $tree,
            $prePruneForest,
            $this->deriveScaffolding($tree, $extractTargetId, $virtualRootWrapped)
        );
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    private function reduceTreeLanguage(array $tree, ChannelContext $channelContext): array
    {
        return array_map(
            fn (StoredElement $element): StoredElement => $this->reduceLanguage($element, $channelContext),
            $tree
        );
    }

    private function reduceLanguage(StoredElement $element, ChannelContext $channelContext): StoredElement
    {
        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->reduceLanguage($child, $channelContext),
                $children
            );
        }

        return $element
            ->withProperties($this->reduceProperties($element, $channelContext))
            ->withSlots($slots);
    }

    /**
     * @return array<string, StoredValue>
     */
    private function reduceProperties(StoredElement $element, ChannelContext $channelContext): array
    {
        if (!$this->typeRegistry->has($element->component)) {
            return $element->properties();
        }

        $declared = $this->typeRegistry->get($element->component)->properties();
        $chain = $channelContext->getLanguageIdChain();
        $properties = [];

        foreach ($element->properties() as $key => $value) {
            $properties[$key] = isset($declared[$key]) && $declared[$key]->type()->translatable()
                ? $this->selectTranslation($element->id, $key, $value, $chain)
                : $value;
        }

        return $properties;
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private function selectTranslation(string $elementId, string $key, StoredValue $value, array $languageIdChain): StoredValue
    {
        $raw = $value->jsonSerialize();

        if (!\is_array($raw) || array_is_list($raw)) {
            throw ContentSystemException::translationShapeInvalid(
                $elementId,
                $key,
                \is_array($raw) ? 'list' : get_debug_type($raw)
            );
        }

        $map = $value->asMap();
        foreach ($languageIdChain as $languageId) {
            if (!\array_key_exists($languageId, $map)) {
                continue;
            }

            $selected = $map[$languageId];
            if (!$selected->isString()) {
                throw ContentSystemException::translationShapeInvalid($elementId, $key, 'a map with a non-string entry');
            }

            return $selected;
        }

        return StoredValue::ofNull();
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    private function resolveTreePlaceholders(array $tree, RenderingSpecification $specification, RenderingMode $mode): array
    {
        if ($mode !== RenderingMode::FULL) {
            return $tree;
        }

        return array_map(
            fn (StoredElement $element): StoredElement => $this->resolvePlaceholders($element, $specification->placeholderValues),
            $tree
        );
    }

    /**
     * The id a partial render extracts, or null when the request addresses the whole layout.
     */
    private function extractTargetId(RenderingSpecification $specification): ?string
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return null;
        }

        return $targetElementId;
    }

    /**
     * Prunes the layout tree to the target element and its dependencies when the `elementId` parameter is present.
     *
     * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow; the pipeline's
     * partial extract removes those ancestors after hydration.
     *
     * It runs on the stored forest, before the lowering, so the discarded subtrees never reach the render
     * model at all.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement>
     */
    private function pruneToTarget(array $elements, ?string $extractTargetId): array
    {
        if ($extractTargetId === null) {
            return $elements;
        }

        return $this->partialRenderer->pruneToTarget($elements, $extractTargetId);
    }

    /**
     * Records what the finishing steps need to know about the tree these steps produced.
     *
     * `virtualRootSurvivedPrune` is read off the post-prune forest, because the wrap decision alone
     * does not answer whether the virtual root is still there: a partial render addressed at an
     * element that needs no page-level context prunes it away. Element ids are unique across roots,
     * so pruning leaves at most one surviving root whenever a target is set and the first root
     * decides. `$extractTargetId` is passed in already normalised — the prune ran on it.
     *
     * @param list<StoredElement> $elements
     */
    private function deriveScaffolding(array $elements, ?string $extractTargetId, bool $virtualRootWrapped): RenderScaffolding
    {
        $virtualRootSurvivedPrune = $virtualRootWrapped
            && $elements !== []
            && $this->virtualRootWrapper->isVirtualRoot($elements[0]);

        return new RenderScaffolding($virtualRootSurvivedPrune, $extractTargetId);
    }

    /**
     * Rewrites the string values of an element's own property map, resolves the placeholders in each of its
     * data requirement's loader config, and recurses into its slot children.
     *
     * A list or map property value is handed on untouched, string leaves inside it included: a placeholder is
     * a property of the authored value, and reaching into a container would resolve tokens the authoring
     * surface never offered to resolve.
     */
    private function resolvePlaceholders(StoredElement $element, PlaceholderValues $values): StoredElement
    {
        $properties = [];
        foreach ($element->properties() as $key => $value) {
            $properties[$key] = $value->isString()
                ? StoredValue::ofString($this->substitute($value->asString(), $values))
                : $value;
        }

        $dataRequirements = [];
        foreach ($element->dataRequirements as $key => $requirement) {
            $dataRequirements[$key] = new DataRequirement(
                $requirement->key,
                $requirement->source,
                $this->configSerializers->decode(
                    $requirement->source,
                    $this->configSerializers->encode($requirement->source, $requirement->config),
                    $values,
                ),
            );
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->resolvePlaceholders($child, $values),
                $children
            );
        }

        return $element->withProperties($properties)->withDataRequirements($dataRequirements)->withSlots($slots);
    }

    /**
     * One pass over the declared keys, no recursion into what a substitution produced. A `{{token}}` whose
     * key carries no value stays verbatim, so an unresolved placeholder is visible rather than blanked.
     */
    private function substitute(string $input, PlaceholderValues $values): string
    {
        foreach ($values->all() as $key => $value) {
            $input = str_replace('{{' . $key . '}}', (string) $value, $input);
        }

        return $input;
    }
}
