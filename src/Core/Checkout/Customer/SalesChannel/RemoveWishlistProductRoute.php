<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Event\WishlistProductRemovedEvent;
use Contena\Core\Checkout\Customer\Extension\RemoveWishlistProductRouteExtension;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class RemoveWishlistProductRoute extends AbstractRemoveWishlistProductRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWishlistCollection> $wishlistRepository
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly EntityRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractRemoveWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/customer/wishlist/delete/{productId}',
        name: 'store-api.customer.wishlist.delete',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: RemoveWishlistProductRouteExtension::NAME,
            extension: new RemoveWishlistProductRouteExtension($productId, $context, $customer),
            function: $this->_delete(...),
        );
    }

    private function _delete(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannelId())) {
            throw CustomerException::customerWishlistNotActivated();
        }

        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $wishlistProductId = $this->getWishlistProductId($wishlistId, $productId, $context);

        $this->productRepository->delete([
            [
                'id' => $wishlistProductId,
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistProductRemovedEvent($wishlistId, $productId, $context));

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, string $customerId): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', $customerId),
                new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            ]));

        $wishlistId = $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistId) {
            throw CustomerException::customerWishlistNotFound();
        }

        return $wishlistId;
    }

    private function getWishlistProductId(string $wishlistId, string $productId, SalesChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('wishlistId', $wishlistId),
                new EqualsFilter('productId', $productId),
            ]));

        $wishlistProductId = $this->productRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistProductId) {
            throw CustomerException::wishlistProductNotFound($productId);
        }

        return $wishlistProductId;
    }
}
