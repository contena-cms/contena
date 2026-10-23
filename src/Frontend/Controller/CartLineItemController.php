<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\AbstractBlogListRoute;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CartLineItemController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly LineItemFactoryInterface $blogLineItemFactory,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly AbstractBlogListRoute $blogListRoute,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
        private readonly CartReorderRoute $cartReorderRoute
    ) {
    }

    #[Route(path: '/checkout/line-item/delete/{id}', name: 'frontend.checkout.line-item.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'DELETE'])]
    public function deleteLineItem(Cart $cart, string $id, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::delete-line-item', function () use ($cart, $id, $request, $context) {
            try {
                if (!$cart->has($id)) {
                    throw CartException::lineItemNotFound($id);
                }

                $cart = $this->cartService->remove($cart, $id, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(path: '/checkout/cart/delete', name: 'frontend.checkout.cart.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function deleteCart(Request $request, ChannelContext $context): Response
    {
        $this->cartService->deleteCart($context);

        return $this->createActionResponse($request);
    }

    /**
     * requires the provided items in the following form
     * 'ids' => [
     *     'firstLineItemId',
     *     'secondLineItemId',
     *     'thirdLineItemId',
     * ]
     */
    #[Route(path: '/checkout/line-item/delete', name: 'frontend.checkout.line-items.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'DELETE'])]
    public function deleteLineItems(Cart $cart, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::delete-line-items', function () use ($cart, $request, $context) {
            try {
                $idData = RequestParamHelper::get($request, 'ids');
                if (!\is_array($idData) || $idData === []) {
                    throw RoutingException::missingRequestParameter('ids');
                }

                $ids = [];
                foreach ($idData as $key => $id) {
                    if (!\is_string($id)) {
                        throw RoutingException::invalidRequestParameter("ids[{$key}]");
                    }

                    $ids[] = $id;
                }

                $cart = $this->cartService->removeItems($cart, $ids, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * It has some individual code for the frontend layouts, like visual
     * error and success messages.
     */
    #[Route(path: '/checkout/promotion/add', name: 'frontend.checkout.promotion.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addPromotion(Cart $cart, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::add-promotion', function () use ($cart, $request, $context) {
            try {
                $code = mb_trim((string) $request->request->get('code'));

                if ($code === '') {
                    $this->addFlash(self::DANGER, $this->trans('error.VIOLATION::IS_BLANK_ERROR'));

                    return $this->createActionResponse($request);
                }

                $lineItem = $this->lineItemFactoryRegistry->create([
                    'referencedId' => $code,
                ], $context);

                $cart = $this->cartService->add($cart, $lineItem, $context);

                $this->traceErrors($cart);
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(path: '/checkout/line-item/change-quantity/{id}', name: 'frontend.checkout.line-item.change-quantity', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function changeQuantity(Cart $cart, string $id, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::change-quantity', function () use ($cart, $id, $request, $context) {
            try {
                $quantity = RequestParamHelper::get($request, 'quantity');

                if ($quantity === null) {
                    throw RoutingException::missingRequestParameter('quantity');
                }

                if (!$cart->has($id)) {
                    throw CartException::lineItemNotFound($id);
                }

                $cart = $this->cartService->changeQuantity($cart, $id, (int) $quantity, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * requires the provided items in the following form
     * 'lineItems' => [
     *     'anyKey' => [
     *         'id' => 'someKey'
     *         'quantity' => 2,
     *     ],
     *     'randomKey' => [
     *         'id' => 'otherKey'
     *         'quantity' => 2,
     *     ]
     * ]
     */
    #[Route(path: '/checkout/line-item/update', name: 'frontend.checkout.line-items.update', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'PATCH'])]
    public function updateLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::update-line-items', function () use ($cart, $requestDataBag, $request, $context) {
            try {
                $lineItems = $requestDataBag->get('lineItems');
                if (!$lineItems instanceof RequestDataBag) {
                    throw RoutingException::missingRequestParameter('lineItems');
                }

                $items = [];
                foreach ($lineItems as $lineItemData) {
                    $items[] = $this->getLineItemArray($lineItemData, null);
                }

                $this->cartService->update($cart, $items, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(path: '/checkout/blog/add-by-number', name: 'frontend.checkout.blog.add-by-number', methods: ['POST'])]
    public function addBlogByNumber(Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::add-blog-by-number', function () use ($request, $context) {
            $number = (string) $request->request->get('number');

            if (mb_trim($number) === '') {
                $this->addFlash(self::DANGER, $this->trans('error.VIOLATION::IS_BLANK_ERROR'));

                return $this->createActionResponse($request);
            }

            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('blogNumber', $number));
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('childCount', 0),
                new EqualsFilter('childCount', null),
            ]));

            $data = $this->blogListRoute->load($criteria, $context)->getBlogs()->getIds();

            if ($data === []) {
                $this->addFlash(self::DANGER, $this->trans(
                    'error.blogNotFound',
                    ['%number%' => $this->htmlSanitizer->sanitize($number, null, true)]
                ));

                return $this->createActionResponse($request);
            }

            $blogId = array_shift($data);

            $blog = $this->blogLineItemFactory->create(['id' => $blogId, 'referencedId' => $blogId], $context);

            $cart = $this->cartService->getCart($context->getToken(), $context);

            $cart = $this->cartService->add($cart, $blog, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => 1]));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * requires the provided items in the following form
     * 'lineItems' => [
     *     'anyKey' => [
     *         'id' => 'someKey'
     *         'quantity' => 2,
     *         'type' => 'someType'
     *     ],
     *     'randomKey' => [
     *         'id' => 'otherKey'
     *         'quantity' => 2,
     *         'type' => 'otherType'
     *     ]
     * ]
     */
    #[Route(path: '/checkout/line-item/add', name: 'frontend.checkout.line-item.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::add-line-item', function () use ($cart, $requestDataBag, $request, $context) {
            /** @var RequestDataBag|null $lineItems */
            $lineItems = $requestDataBag->get('lineItems');
            if (!$lineItems) {
                throw RoutingException::missingRequestParameter('lineItems');
            }

            $count = 0;

            try {
                $items = [];
                /** @var RequestDataBag $lineItemData */
                foreach ($lineItems as $lineItemData) {
                    try {
                        $item = $this->lineItemFactoryRegistry->create($this->getLineItemArray($lineItemData, [
                            'quantity' => 1,
                            'stackable' => true,
                            'removable' => true,
                        ]), $context);
                        $count += $item->getQuantity();

                        $items[] = $item;
                    } catch (CartException $e) {
                        if ($e->getErrorCode() === CartException::CART_INVALID_LINE_ITEM_QUANTITY_CODE) {
                            $this->addFlash(
                                self::DANGER,
                                $this->trans(
                                    'error.CHECKOUT__CART_INVALID_LINE_ITEM_QUANTITY',
                                    [
                                        '%quantity%' => $e->getParameter('quantity'),
                                    ]
                                )
                            );

                            return $this->createActionResponse($request);
                        }

                        throw $e;
                    }
                }

                $cart = $this->cartService->add($cart, $items, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
                }
            } catch (BlogNotFoundException|RoutingException) {
                $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(
        path: '/checkout/cart/reorder/{orderId}',
        name: 'frontend.checkout.cart.reorder',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: ['POST']
    )]
    public function reorder(string $orderId, Cart $cart, Request $request, ChannelContext $context): Response
    {
        return Profiler::trace('cart::reorder', function () use ($orderId, $cart, $request, $context) {
            $quantityBefore = $cart->getLineItems()->getTotalQuantity();

            try {
                $cart = $this->cartReorderRoute->reorder($orderId, $request, $cart, $context)->getCart();

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', [
                        '%count%' => $cart->getLineItems()->getTotalQuantity() - $quantityBefore,
                    ]));
                }
            } catch (RateLimitExceededException $exception) {
                $this->addFlash(self::INFO, $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]));
            } catch (CartException|BlogNotFoundException|RoutingException) {
                $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
            }

            return $this->createActionResponse($request);
        });
    }

    private function traceErrors(Cart $cart): bool
    {
        $this->filterSuccessErrorMessages($cart);

        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors($cart, static fn (Error $error) => $error->isPersistent());

        return true;
    }

    /**
     * @param ?array{quantity: int, stackable: bool, removable: bool} $defaultValues
     *
     * @return array<string, mixed>
     */
    private function getLineItemArray(RequestDataBag $lineItemData, ?array $defaultValues): array
    {
        if ($lineItemData->has('payload')) {
            $lineItemData->set('payload', $this->normalizePayload($lineItemData->get('payload')));
        }

        $lineItemArray = $lineItemData->all();
        if ($defaultValues !== null) {
            $lineItemArray = array_replace($defaultValues, $lineItemArray);
        }

        if (isset($lineItemArray['quantity'])) {
            $lineItemArray['quantity'] = (int) $lineItemArray['quantity'];
        }

        if (isset($lineItemArray['stackable'])) {
            $lineItemArray['stackable'] = (bool) $lineItemArray['stackable'];
        }

        if (isset($lineItemArray['removable'])) {
            $lineItemArray['removable'] = (bool) $lineItemArray['removable'];
        }

        if (isset($lineItemArray['priceDefinition']['quantity'])) {
            $lineItemArray['priceDefinition']['quantity'] = (int) $lineItemArray['priceDefinition']['quantity'];
        }

        if (isset($lineItemArray['priceDefinition']['isCalculated'])) {
            $lineItemArray['priceDefinition']['isCalculated'] = (int) $lineItemArray['priceDefinition']['isCalculated'];
        }

        return $lineItemArray;
    }

    /**
     * @throws RoutingException
     * @throws \JsonException
     *
     * @return array<string, mixed>
     */
    private function normalizePayload(mixed $payload): array
    {
        return match (true) {
            $payload instanceof RequestDataBag => $payload->all(),
            \is_array($payload) => $payload,
            \is_string($payload) && mb_strlen($payload, '8bit') > 256 * 1024 => throw RoutingException::invalidRequestParameter('payload'),
            \is_string($payload) => json_decode($payload, true, 512, \JSON_THROW_ON_ERROR),
            default => throw RoutingException::invalidRequestParameter('payload'),
        };
    }

    /**
     * we basically show all cart errors or notices
     * at the moments it's not possible to show success messages with "green" color
     * from the cart...thus it has to be done in the frontend level
     * so if we have a promotion added notice, we simply convert this to
     * a success flash message
     */
    private function filterSuccessErrorMessages(Cart $cart): void
    {
        foreach ($cart->getErrors() as $key => $error) {
            if ($error instanceof PromotionCartAddedInformationError) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.codeAddedSuccessful'));
                $cart->getErrors()->remove($key);
            }
        }
    }
}
