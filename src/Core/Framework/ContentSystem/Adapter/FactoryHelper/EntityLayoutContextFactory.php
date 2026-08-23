<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @final
 */
class EntityLayoutContextFactory
{
    public function __construct(
        private readonly EntityLayoutResolver $layoutResolver,
        private readonly RootContextMapper $rootContextMapper,
    ) {
    }

    /**
     * The root-ambient context this entity assignment supplies to a layout's top-level elements,
     * derived from the definition's page data requirements via the one shared mapping path.
     *
     * @return list<ProvidedContext>
     */
    public function providedRootContext(AbstractContentLayoutAssignableDefinition $definition): array
    {
        return $this->rootContextMapper->map($definition->getPageDataRequirements());
    }

    public function supports(string $path, AbstractContentLayoutAssignableDefinition $definition): bool
    {
        $path = '/' . ltrim($path, '/');
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        return str_starts_with($path, $pathPrefix);
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function resolveLayoutId(
        string $path,
        ChannelContext $context,
        EntityRepository $repository,
        AbstractContentLayoutAssignableDefinition $definition
    ): string {
        $entityId = $this->extractEntityId($path, $definition);

        $layoutId = $this->layoutResolver->findLayoutId(
            $definition->getContentLayoutEntityIdField(),
            $entityId,
            $context,
            $repository
        );

        if ($layoutId === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                $definition->getContentLayoutEntityType(),
                $entityId,
                $context->getChannel()->getId()
            );
        }

        return $layoutId;
    }

    public function resolveSpecificationData(
        string $path,
        Request $request,
        ChannelContext $context,
        AbstractContentLayoutAssignableDefinition $definition
    ): SpecificationData {
        return $this->buildSpecificationData(
            $this->extractEntityId($path, $definition),
            $request,
            $context,
            $definition,
        );
    }

    public function buildSpecificationData(
        string $entityId,
        Request $request,
        ChannelContext $context,
        AbstractContentLayoutAssignableDefinition $definition
    ): SpecificationData {
        return new SpecificationData(
            dataRequirements: array_values($definition->getPageDataRequirements()),
            placeholderValues: $this->layoutResolver->resolvePlaceholders(
                $definition->getContentLayoutEntityIdField(),
                $entityId,
                $request,
            ),
        );
    }

    public function resolveTargetElementId(Request $request): ?string
    {
        $elementId = $request->query->get('elementId');

        if (\is_string($elementId) && $elementId !== '') {
            return $elementId;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, AbstractContentLayoutAssignableDefinition $definition): array
    {
        $entityId = $this->extractEntityId($path, $definition);

        return $definition->getCacheTags($entityId);
    }

    /**
     * @throws ContentSystemException If path doesn't match route pattern
     */
    private function extractEntityId(string $path, AbstractContentLayoutAssignableDefinition $definition): string
    {
        $path = '/' . ltrim($path, '/');
        $routePattern = $definition->getContentLayoutRoutePattern();
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        $route = new Route($pathPrefix . $routePattern);
        $collection = new RouteCollection();
        $collection->add('entity', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            $expectedFormat = $definition->getContentLayoutPathPrefix() . $definition->getContentLayoutRoutePattern();
            throw ContentSystemException::invalidEntityPath(
                $definition->getContentLayoutEntityType(),
                $path,
                $expectedFormat
            );
        }

        $entityIdField = $definition->getContentLayoutEntityIdField();

        return $parameters[$entityIdField];
    }
}
