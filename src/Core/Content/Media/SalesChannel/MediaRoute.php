<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\SalesChannel;

use Contena\Core\Content\Media\Extension\MediaRouteExtension;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class MediaRoute extends AbstractMediaRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'media-' . $id;
    }

    public function getDecorated(): AbstractMediaRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/media',
        name: 'store-api.media.detail',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, SalesChannelContext $context): MediaRouteResponse
    {
        return $this->extensions->publish(
            name: MediaRouteExtension::NAME,
            extension: new MediaRouteExtension($request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context): MediaRouteResponse
    {
        $ids = RequestParamHelper::get($request, 'ids', []);
        if (!\is_array($ids) || $ids === []) {
            throw MediaException::emptyMediaId();
        }

        $mediaCollection = $this->findMediaByIds($ids, $context->getContext());

        $tags = [];
        foreach ($mediaCollection as $media) {
            $tags[] = self::buildName($media->getId());
        }
        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }

        return new MediaRouteResponse($mediaCollection);
    }

    /**
     * @param array<string> $ids
     */
    private function findMediaByIds(array $ids, Context $context): MediaCollection
    {
        $criteria = new Criteria($ids);
        $criteria->addFilter(new EqualsFilter('private', false));

        $mediaSearchResult = $this->mediaRepository
            ->search($criteria, $context);

        return $mediaSearchResult->getEntities();
    }
}
