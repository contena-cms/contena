<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\Content\Blog\Extension\BlogPurchaseLimitRouteExtension;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogPurchaseLimitRoute extends AbstractBlogPurchaseLimitRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly AbstractBlogMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractBlogPurchaseLimitRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/purchase-limit',
        name: 'store-api.blog.purchase-limit',
        methods: [Request::METHOD_GET],
        priority: 1, // keeping priority higher than in \Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute
    )]
    public function readBlogsPurchaseLimit(Request $request, ChannelContext $context): BlogPurchaseLimitRouteResponse
    {
        return $this->extensions->publish(
            name: BlogPurchaseLimitRouteExtension::NAME,
            extension: new BlogPurchaseLimitRouteExtension($request, $context),
            function: $this->_readBlogsPurchaseLimit(...),
        );
    }

    private function _readBlogsPurchaseLimit(Request $request, ChannelContext $context): BlogPurchaseLimitRouteResponse
    {
        /** @var array<string> $ids */
        $ids = $request->query->all('ids');

        if ($ids === []) {
            throw BlogException::missingRequestParameter('ids');
        }

        $criteria = new Criteria($ids);
        $criteria->setTitle('blog-purchase-limit-route');

        $criteria->addFields([
            'minPurchase',
            'maxPurchase',
            'purchaseSteps',
            'isCloseout',
            'stock',
        ]);

        $blogs = $this->blogRepository->search($criteria, $context)->getEntities();

        $results = new BlogPurchaseLimitCollection();

        foreach ($blogs as $blog) {
            $maxPurchase = $this->maxPurchaseCalculator->calculate($blog, $context);
            $minPurchase = $blog->get('minPurchase') ?? 1;
            $purchaseSteps = $blog->get('purchaseSteps') ?? 1;
            $stock = $blog->get('stock');

            $results->add(new BlogPurchaseLimit(
                $blog->getId(),
                $minPurchase,
                $purchaseSteps,
                $maxPurchase,
                $stock,
            ));
        }

        return new BlogPurchaseLimitRouteResponse($results);
    }
}
