<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface as SeoUrlRouteConfigRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Api\ChannelApiResponseListener;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class ChannelApiSeoResolver implements EventSubscriberInterface
{
    /**
     * @param ChannelRepository<SeoUrlCollection> $channelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly ChannelRepository $channelRepository,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly ChannelDefinitionInstanceRegistry $channelDefinitionInstanceRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly EntityRouteResolver $entityRouteResolver
    ) {
    }

    /**
     * This subscriber has to trigger before the {@see ChannelApiResponseListener},
     * because it requires access to the `ChannelApiResponse`'s struct object, which is not available after encoding it.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['addSeoInformation', 11000],
        ];
    }

    public function addSeoInformation(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof ChannelApiResponse) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->headers->has(PlatformRequest::HEADER_INCLUDE_SEO_URLS)) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof ChannelContext) {
            // This is likely the case for routes with the `auth_required` option set to `false`,
            // where the channel ID and context are not resolved by access token by the other listeners.
            return;
        }

        $dataBag = new SeoResolverData();

        $this->find($dataBag, $response->getObject());
        $this->enrich($dataBag, $context);
    }

    private function find(SeoResolverData $data, Struct $struct): void
    {
        if ($struct instanceof AggregationResultCollection) {
            foreach ($struct as $item) {
                $this->findStruct($data, $item);
            }
        }

        if ($struct instanceof EntitySearchResult) {
            foreach ($struct->getEntities() as $entity) {
                $this->findStruct($data, $entity);
            }

            foreach ($struct->getExtensions() as $extension) {
                $this->findStruct($data, $extension);
            }
        }

        if ($struct instanceof Collection) {
            foreach ($struct as $item) {
                $this->findStruct($data, $item);
            }
        }

        $this->findStruct($data, $struct);
    }

    private function findStruct(SeoResolverData $data, Struct|RenderedElement $struct): void
    {
        if ($struct instanceof RenderedElement) {
            $this->findRenderedElement($data, $struct);

            return;
        }

        if ($struct instanceof Entity) {
            $definition = $this->definitionInstanceRegistry->getByEntityClass($struct) ?? $this->channelDefinitionInstanceRegistry->getByEntityClass($struct);

            if ($definition && $definition->isSeoAware()) {
                $data->add($definition->getEntityName(), $struct);
            }
        }

        foreach ($struct->getVars() as $item) {
            if ($item instanceof EntitySearchResult) {
                $this->find($data, $item);
            } elseif ($item instanceof Collection || \is_array($item)) {
                foreach ($item as $collectionItem) {
                    if ($collectionItem instanceof Struct || $collectionItem instanceof RenderedElement) {
                        $this->findStruct($data, $collectionItem);
                    }
                }
            } elseif ($item instanceof Struct || $item instanceof RenderedElement) {
                $this->findStruct($data, $item);
            }
        }
    }

    /**
     * RenderedElement is not a Struct, so the regular struct walker cannot inspect its properties and slots.
     */
    private function findRenderedElement(SeoResolverData $data, RenderedElement $element): void
    {
        foreach ($element->properties as $value) {
            if ($value instanceof Struct) {
                $this->findStruct($data, $value);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            foreach ($value as $item) {
                if ($item instanceof Struct) {
                    $this->findStruct($data, $item);
                }
            }
        }

        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                $this->findRenderedElement($data, $child);
            }
        }
    }

    private function enrich(SeoResolverData $data, ChannelContext $context): void
    {
        foreach ($data->getEntities() as $definition) {
            $definition = (string) $definition;

            $ids = $data->getIds($definition);
            $routeNames = $this->getRouteNames($definition, $context);
            if ($routeNames === []) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('isCanonical', true));
            $criteria->addFilter(new EqualsAnyFilter('routeName', $routeNames));
            $criteria->addFilter(new EqualsAnyFilter('foreignKey', $ids));
            $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
            $criteria->addSorting(new FieldSorting('channelId'));

            foreach ($this->channelRepository->search($criteria, $context)->getEntities() as $url) {
                $entities = $data->getAll($definition, $url->getForeignKey());

                foreach ($entities as $entity) {
                    if (!\method_exists($entity, 'getSeoUrls') || !\method_exists($entity, 'setSeoUrls')) {
                        break;
                    }

                    if ($entity->getSeoUrls() === null) {
                        $entity->setSeoUrls(new SeoUrlCollection());
                    }

                    if (!$entity->getSeoUrls() instanceof SeoUrlCollection) {
                        break;
                    }

                    $seoUrlCollection = $entity->getSeoUrls();
                    $seoUrlCollection->add($url);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getRouteNames(string $entityName, ChannelContext $context): array
    {
        $routeNames = array_values(array_map(
            static fn (SeoUrlRouteConfigRoute $seoUrlRoute) => $seoUrlRoute->getConfig()->getRouteName(),
            $this->seoUrlRouteRegistry->findByDefinition($entityName)
        ));

        if ($context->getChannel()->getTypeId() !== Defaults::CHANNEL_TYPE_API) {
            return $routeNames;
        }

        // Headless channels persist SEO URLs against the Channel API route family. Frontend route names remain
        // in the filter as a fallback for entities without a Channel API counterpart.
        try {
            return array_values(array_unique([
                $this->entityRouteResolver->getRouteNameForEntityName($entityName, $context->getChannel()->getTypeId()),
                ...$routeNames,
            ]));
        } catch (SeoUrlRouteConfigException) {
            return $routeNames;
        }
    }
}
