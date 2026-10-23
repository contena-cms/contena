<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface as SeoUrlRouteConfigRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Api\StoreApiResponseListener;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\StoreApiResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class StoreApiSeoResolver implements EventSubscriberInterface
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
     * This subscriber has to trigger before the {@see StoreApiResponseListener},
     * because it requires access to the `StoreApiResponse`'s struct object, which is not available after encoding it.
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

        if (!$response instanceof StoreApiResponse) {
            return;
        }

        $request = $event->getRequest();

        // an empty value counts as absent, like in the HTTP cache key
        if ($request->headers->get(PlatformRequest::HEADER_INCLUDE_SEO_URLS, '') === '') {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof ChannelContext) {
            // This is likely the case for routes with the `auth_required` option set to `false`,
            // where the sales-channel-id and context is not resolved by access-token by the other listeners.
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

        /** @deprecated tag:v6.8.0 - Remove the EntitySearchResult exclusion once it no longer extends Collection. */
        if ($struct instanceof Collection && !$struct instanceof EntitySearchResult) {
            foreach ($struct as $item) {
                $this->findStruct($data, $item);
            }
        }

        $this->findStruct($data, $struct);
    }

    private function findStruct(SeoResolverData $data, Struct $struct): void
    {
        if ($struct instanceof Entity) {
            $definition = $this->definitionInstanceRegistry->getByEntityClass($struct) ?? $this->channelDefinitionInstanceRegistry->getByEntityClass($struct);

            if ($definition && $definition->isSeoAware()) {
                $data->add($definition->getEntityName(), $struct);
            }
        }

        foreach ($struct->getVars() as $item) {
            /** @deprecated tag:v6.8.0 - Fold the EntitySearchResult branch into the Struct one once it no longer extends Collection. */
            if ($item instanceof EntitySearchResult) {
                $this->findStruct($data, $item);
            } elseif ($item instanceof Collection || \is_array($item)) {
                foreach ($item as $collectionItem) {
                    if ($collectionItem instanceof Struct) {
                        $this->findStruct($data, $collectionItem);
                    }
                }
            } elseif ($item instanceof Struct) {
                $this->findStruct($data, $item);
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

        // Headless sales channels persist their SEO URLs against the store-api route family. The frontend
        // route names stay in the filter as a fallback for entities without a store-api counterpart.
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
