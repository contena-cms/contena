<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Called by RenderingSpecificationFactory to assemble a RenderingSpecification
 * from discrete resolution steps.
 *
 * @internal
 */
abstract class AbstractSpecificationSource
{
    abstract public function supports(string $path, Request $request, ChannelContext $context): bool;

    abstract public function resolveLayoutId(string $path, Request $request, ChannelContext $context): string;

    abstract public function resolveSpecificationData(string $path, Request $request, ChannelContext $context): SpecificationData;

    abstract public function resolveTargetElementId(string $path, Request $request, ChannelContext $context): ?string;

    /**
     * @return list<string>
     */
    abstract public function resolveCacheTags(string $path, Request $request, ChannelContext $context): array;

    /**
     * Whether this source can resolve a layout-free specification for the given entity type.
     * Entity sources override this; domain-aware sources (header/footer) keep the default.
     */
    public function supportsEntityType(string $entityType): bool
    {
        return false;
    }

    /**
     * Assembles specification data from an entity id directly, without a layout assignment.
     * Only ever called on sources whose supportsEntityType() returned true.
     */
    public function resolveSpecificationDataForEntity(string $entityId, Request $request, ChannelContext $context): SpecificationData
    {
        throw ContentSystemException::entityTypeResolutionUnsupported();
    }

    /**
     * The root context this source supplies to a layout's top-level elements. Entity sources override it
     * with their page data requirements; header/footer sources expose no root-ambient context. Typed on
     * Context (not ChannelContext): the mapping is config/type-only and reads no Channel state.
     *
     * @return list<ProvidedContext>
     */
    public function providedRootContext(Context $context): array
    {
        return [];
    }
}
