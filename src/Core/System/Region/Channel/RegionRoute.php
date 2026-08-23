<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Region\Event\RegionCriteriaEvent;
use Contena\Core\System\Region\RegionCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class RegionRoute extends AbstractRegionRoute
{
    final public const string ALL_TAG = 'region-route';

    /**
     * @internal
     *
     * @param EntityRepository<RegionCollection> $regionRepository
     */
    public function __construct(
        private readonly EntityRepository $regionRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'region-route-' . $id;
    }

    #[Route(
        path: '/channel-api/region/{countryId}',
        name: 'channel-api.region',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CountryDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $countryId, Request $request, Criteria $criteria, ChannelContext $context): RegionRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($countryId), self::ALL_TAG);

        $criteria->addFilter(
            new EqualsFilter('countryId', $countryId),
            new EqualsFilter('active', true),
        );
        if (!$criteria->hasEqualsFilter('parentId') && !$criteria->hasEqualsFilter('region.parentId')) {
            $criteria->addFilter(new EqualsFilter('parentId', null));
        }
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING, true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $this->dispatcher->dispatch(new RegionCriteriaEvent($countryId, $request, $criteria, $context));
        $regions = $this->regionRepository->search($criteria, $context->getContext());

        return new RegionRouteResponse($regions);
    }

    protected function getDecorated(): AbstractRegionRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
