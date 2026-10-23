<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class CartOrderEventSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserAddressCollection>
     */
    private EntityRepository $userAddressRepository;

    private AbstractChannelContextFactory $contextFactory;

    private ChannelContextPersister $contextPersister;

    private CartService $cartService;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            // login() creates the user with an address in a country of the default sales channel
            'countries' => [['id' => $this->getValidCountryId(null)], ['id' => $this->getValidCountryId()]],
        ]);

        $this->userAddressRepository = static::getContainer()->get('user_address.repository');
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->contextFactory = $contextFactory;
        $this->contextPersister = static::getContainer()->get(ChannelContextPersister::class);
        $this->cartService = static::getContainer()->get(CartService::class);
    }

    public function testShippingAndBillingAddressResetAfterCheckout(): void
    {
        $userId = $this->login($this->browser);
        $this->addAdditionalAddress($userId);

        $blogBuilder = new BlogBuilder($this->ids, 'p1');
        $blogBuilder->price(100)->visibility($this->ids->get('sales-channel'));
        $blogBuilder->write(static::getContainer());
        $blogId = $this->ids->get('p1');

        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['userId' => $userId]);

        $user = $context->getUser();
        static::assertNotNull($user);
        $defaultShippingAddressId = $user->getDefaultShippingAddressId();
        $defaultBillingAddressId = $user->getDefaultBillingAddressId();

        $shippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($shippingAddress, 'Shipping location should have an address');
        $initialShippingId = $shippingAddress->getId();
        static::assertSame(
            $defaultShippingAddressId,
            $initialShippingId,
            \sprintf('Initial shipping address (%s) should be default address (%s)', $initialShippingId, $defaultShippingAddressId)
        );

        $cart = $this->cartService->createNew($token);
        $lineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId);
        $lineItem->setStackable(true);
        $lineItem->setQuantity(1);
        $cart = $this->cartService->add($cart, $lineItem, $context);

        $additionalAddressId = $this->ids->get('additional-address');
        $this->contextPersister->save(
            $token,
            [
                ChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
                ChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
            ],
            $this->ids->get('sales-channel'),
            $userId
        );

        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), [
            'userId' => $userId,
            ChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
            ChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
        ]);
        $currentShippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($currentShippingAddress, 'Shipping location should have an address');
        $currentShippingId = $currentShippingAddress->getId();
        static::assertSame(
            $additionalAddressId,
            $currentShippingId,
            \sprintf('Shipping address should be changed to additional address (%s) but got (%s)', $additionalAddressId, $currentShippingId)
        );

        $orderId = $this->cartService->order($cart, $context, new RequestDataBag());
        static::assertNotSame('', $orderId);

        $freshContext = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['userId' => $userId]);

        $actualShippingAddress = $freshContext->getShippingLocation()->getAddress();
        static::assertNotNull($actualShippingAddress, 'Shipping location should have an address');
        $actualShippingAddressId = $actualShippingAddress->getId();

        $actualBillingAddress = $freshContext->getUser()?->getActiveBillingAddress();
        static::assertNotNull($actualBillingAddress, 'User should have an active billing address');
        $actualBillingAddressId = $actualBillingAddress->getId();

        $user = $freshContext->getUser();
        static::assertNotNull($user, 'User should be loaded in context');

        static::assertSame(
            $defaultShippingAddressId,
            $actualShippingAddressId,
            \sprintf('Expected shipping address to be reset to user default (%s) but got %s', $defaultShippingAddressId, $actualShippingAddressId)
        );

        static::assertSame(
            $defaultBillingAddressId,
            $actualBillingAddressId,
            \sprintf('Expected billing address to be reset to user default (%s) but got %s', $defaultBillingAddressId, $actualBillingAddressId)
        );
    }

    public function testShippingAndBillingAddressResetAfterDeleteCartStoreApi(): void
    {
        $userId = $this->login($this->browser);
        $this->addAdditionalAddress($userId);

        $blogBuilder = new BlogBuilder($this->ids, 'p1');
        $blogBuilder->price(100)->visibility($this->ids->get('sales-channel'));
        $blogBuilder->write(static::getContainer());
        $blogId = $this->ids->get('p1');

        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['userId' => $userId]);

        $user = $context->getUser();
        static::assertNotNull($user);
        $defaultShippingAddressId = $user->getDefaultShippingAddressId();
        $defaultBillingAddressId = $user->getDefaultBillingAddressId();

        $cart = $this->cartService->createNew($token);
        $lineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId);
        $lineItem->setStackable(true);
        $lineItem->setQuantity(1);
        $this->cartService->add($cart, $lineItem, $context);

        $additionalAddressId = $this->ids->get('additional-address');
        $this->contextPersister->save(
            $token,
            [
                ChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
                ChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
            ],
            $this->ids->get('sales-channel'),
            $userId
        );

        $context = $this->contextFactory->create($token, $this->ids->get('sales-channel'), [
            'userId' => $userId,
            ChannelContextService::SHIPPING_ADDRESS_ID => $additionalAddressId,
            ChannelContextService::BILLING_ADDRESS_ID => $additionalAddressId,
        ]);
        $currentShippingAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($currentShippingAddress, 'Shipping location should have an address');
        $currentShippingId = $currentShippingAddress->getId();
        static::assertSame(
            $additionalAddressId,
            $currentShippingId,
            \sprintf('Shipping address should be changed to additional address (%s) but got (%s)', $additionalAddressId, $currentShippingId)
        );

        $this->browser->request('DELETE', '/store-api/checkout/cart');
        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $freshContext = $this->contextFactory->create($token, $this->ids->get('sales-channel'), ['userId' => $userId]);

        $actualShippingAddress = $freshContext->getShippingLocation()->getAddress();
        static::assertNotNull($actualShippingAddress, 'Shipping location should have an address');
        $actualShippingAddressId = $actualShippingAddress->getId();

        $actualBillingAddress = $freshContext->getUser()?->getActiveBillingAddress();
        static::assertNotNull($actualBillingAddress, 'User should have an active billing address');
        $actualBillingAddressId = $actualBillingAddress->getId();

        $user = $freshContext->getUser();
        static::assertNotNull($user, 'User should be loaded in context');

        static::assertSame(
            $defaultShippingAddressId,
            $actualShippingAddressId,
            \sprintf('Expected shipping address to be reset to user default (%s) but got %s after cart deletion', $defaultShippingAddressId, $actualShippingAddressId)
        );

        static::assertSame(
            $defaultBillingAddressId,
            $actualBillingAddressId,
            \sprintf('Expected billing address to be reset to user default (%s) but got %s after cart deletion', $defaultBillingAddressId, $actualBillingAddressId)
        );
    }

    private function addAdditionalAddress(string $userId): void
    {
        $additionalAddressId = $this->ids->get('additional-address');

        $address = [
            'id' => $additionalAddressId,
            'userId' => $userId,
            'firstName' => 'Test',
            'lastName' => 'User',
            'street' => 'Additional Street 2',
            'city' => 'Additional City',
            'zipcode' => '54321',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
        ];

        $this->userAddressRepository->create([$address], Context::createDefaultContext());
    }
}
