<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Language\Extension\LanguageRouteExtension;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LanguageRoute extends AbstractLanguageRoute
{
    final public const ALL_TAG = 'language-route';

    /**
     * @internal
     *
     * @param ChannelRepository<LanguageCollection> $repository
     */
    public function __construct(
        private readonly ChannelRepository $repository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'language-route-' . $id;
    }

    public function getDecorated(): AbstractLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/language',
        name: 'store-api.language',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => LanguageDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): LanguageRouteResponse
    {
        return $this->extensions->publish(
            name: LanguageRouteExtension::NAME,
            extension: new LanguageRouteExtension($request, $context, $criteria),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria): LanguageRouteResponse => $this->_load($context, $criteria),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria): LanguageRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getChannelId()), self::ALL_TAG);

        $criteria->addAssociation('translationCode');

        return new LanguageRouteResponse(
            $this->repository->search($criteria, $context)
        );
    }
}
