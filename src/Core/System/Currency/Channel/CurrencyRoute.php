<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Currency\Extension\CurrencyRouteExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CurrencyRoute extends AbstractCurrencyRoute
{
    final public const ALL_TAG = 'currency-route';

    /**
     * @internal
     *
     * @param ChannelRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly ChannelRepository $currencyRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractCurrencyRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $channelId): string
    {
        return 'currency-route-' . $channelId;
    }

    #[Route(
        path: '/channel-api/currency',
        name: 'channel-api.currency',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CurrencyDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): CurrencyRouteResponse
    {
        return $this->extensions->publish(
            name: CurrencyRouteExtension::NAME,
            extension: new CurrencyRouteExtension($request, $context, $criteria),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria): CurrencyRouteResponse => $this->_load($context, $criteria),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria): CurrencyRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getChannelId()), self::ALL_TAG);

        return new CurrencyRouteResponse($this->currencyRepository->search($criteria, $context)->getEntities());
    }
}
