<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Diagnostics;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;

/**
 * Maps a bound source's page data requirements to the layout's root-ambient context, by resolving each
 * requirement's produced FQCN via its data loader. Every entry is minted broadcast Single, marked
 * root-ambient ({@see ProvidedContext::$root}) and carrying no provider element id: root context is
 * ambient rather than provided from an element address.
 * One mapping path, shared by the entity sources' providedRootContext override and the diagnostics core.
 *
 * @internal
 *
 * @final
 */
class RootContextMapper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    /**
     * @param list<DataRequirement> $requirements
     *
     * @return list<ProvidedContext>
     */
    public function map(array $requirements): array
    {
        $contexts = [];

        foreach ($requirements as $requirement) {
            $contexts[] = new ProvidedContext(
                contextKey: $requirement->key,
                fqcn: $this->resolveType($requirement),
                contextType: ContextType::Single,
                providerElementId: null,
                distribution: DistributionStrategy::Broadcast,
                root: true,
            );
        }

        return $contexts;
    }

    /**
     * The concrete FQCN a requirement's configured loader produces.
     *
     * @throws ContentSystemException for an unregistered source or an unknown entity; the diagnostics core
     *                                catches the client-defect codes and maps them to an invalid_config violation
     */
    public function resolveType(DataRequirement $requirement): string
    {
        return $this->dataLoaderProvider->get($requirement->source)->resolveProducedType($requirement->config);
    }
}
