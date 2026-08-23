<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Channel;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
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
        path: '/channel-api/media',
        name: 'channel-api.media.detail',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, ChannelContext $context): MediaRouteResponse
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
