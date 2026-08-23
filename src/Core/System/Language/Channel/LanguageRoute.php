<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class LanguageRoute extends AbstractLanguageRoute
{
    final public const string ALL_TAG = 'language-route';

    /**
     * @internal
     *
     * @param ChannelRepository<LanguageCollection> $repository
     */
    public function __construct(
        private readonly ChannelRepository $repository,
        private readonly CacheTagCollector $cacheTagCollector,
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
        path: '/channel-api/language',
        name: 'channel-api.language',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => LanguageDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): LanguageRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($context->getChannelId()), self::ALL_TAG);

        $criteria->addAssociation('translationCode');

        return new LanguageRouteResponse(
            $this->repository->search($criteria, $context)
        );
    }
}
