<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Country\Event\CountryCriteriaEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CountryRoute extends AbstractCountryRoute
{
    final public const string ALL_TAG = 'country-route';

    /**
     * @internal
     *
     * @param ChannelRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly ChannelRepository $countryRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-route-' . $id;
    }

    #[Route(
        path: '/channel-api/country',
        name: 'channel-api.country',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CountryDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, Criteria $criteria, ChannelContext $context): CountryRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getChannelId()), self::ALL_TAG);

        $criteria->setTitle('country-route');
        $criteria->addFilter(new EqualsFilter('active', true));

        $this->dispatcher->dispatch(new CountryCriteriaEvent($request, $criteria, $context));
        $result = $this->countryRepository->search($criteria, $context);

        return new CountryRouteResponse($result);
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
