<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class CartReorderRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $channelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->channelId = $this->ids->create('sales-channel');

        $this->browser = $this->createCustomChannelBrowser(['id' => $this->channelId]);
        $this->assignChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);
        $this->ids->set('user', $userId);

        $this->browser->request('POST', '/store-api/account/login', ['email' => $email, 'password' => 'contena']);

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testBlogsOfMyOwnOrderAreAddedToTheCart(): void
    {
        $blogId = $this->createBlog();
        $orderId = $this->createOrder($this->ids->get('user'), [$blogId]);

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertSame('cart', $response['apiAlias']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame($blogId, $response['lineItems'][0]['referencedId']);
        static::assertSame($blogId, $response['lineItems'][0]['id'], 'the cart id is the blog id, as a normal add to cart posts it');
    }

    public function testTheSameBlogInSeveralOrderRowsBecomesOneCartRow(): void
    {
        $blogId = $this->createBlog();
        $orderId = $this->createOrder($this->ids->get('user'), [$blogId, $blogId]);

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);
        static::assertCount(1, $response['lineItems'], $content);
        static::assertSame(2, $response['lineItems'][0]['quantity']);
    }

    public function testReorderingTwiceStacksOntoTheSameCartRow(): void
    {
        $blogId = $this->createBlog();
        $orderId = $this->createOrder($this->ids->get('user'), [$blogId]);

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);
        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);
        static::assertCount(1, $response['lineItems'], $content);
        static::assertSame(2, $response['lineItems'][0]['quantity']);
    }

    public function testThePersistedOrderPayloadDoesNotTravelIntoTheCart(): void
    {
        $blogId = $this->createBlog();
        $orderId = $this->createOrder($this->ids->get('user'), [$blogId]);

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE order_line_item SET payload = JSON_SET(payload, "$.myPluginKey", "stale") WHERE order_id = :orderId',
            ['orderId' => Uuid::fromHexToBytes($orderId)]
        );

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);
        static::assertCount(1, $response['lineItems'], $content);
        static::assertArrayNotHasKey('myPluginKey', $response['lineItems'][0]['payload'] ?? []);
    }

    public function testUnavailableBlogsAreSkippedInsteadOfFailing(): void
    {
        $availableId = $this->createBlog();
        $deactivatedId = $this->createBlog(active: false);
        $invisibleId = $this->createBlog(visible: false);
        $deletedId = $this->createBlog();

        $orderId = $this->createOrder($this->ids->get('user'), [$availableId, $deactivatedId, $invisibleId, $deletedId]);

        static::getContainer()->get('blog.repository')->delete([['id' => $deletedId]], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $content = (string) $this->browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        // an unbuyable blog must not break the reorder, nor reach the tax calculation
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), $content);
        static::assertCount(1, $response['lineItems'], 'only the still available blog may end up in the cart');
        static::assertSame($availableId, $response['lineItems'][0]['referencedId']);
        static::assertCount(3, $response['errors'], 'every skipped blog is reported as a cart error');
    }

    public function testPromotionLineItemsAreNotReAdded(): void
    {
        $blogId = $this->createBlog();
        // a real blog, so dropping the type guard re-adds it instead of failing on a missing blog
        $promotionReferencedId = $this->createBlog();
        $orderId = $this->createOrder($this->ids->get('user'), [$blogId], $promotionReferencedId);

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $orderId);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response['lineItems']);
        static::assertSame($blogId, $response['lineItems'][0]['referencedId']);
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $response['lineItems'][0]['type']);
    }

    public function testOrderOfAnotherUserIsNotFound(): void
    {
        $blogId = $this->createBlog();
        $foreignOrderId = $this->createOrder($this->createUser(Uuid::randomHex() . '@example.com'), [$blogId]);

        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . $foreignOrderId);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $this->browser->request('POST', '/store-api/checkout/cart/reorder/' . Uuid::randomHex());

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    private function createBlog(bool $active = true, bool $visible = true): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test blog',
            'active' => $active,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'taxId' => $this->getValidTaxId(),
        ];

        if ($visible) {
            $data['visibilities'] = [
                ['channelId' => $this->channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ];
        }

        static::getContainer()->get('blog.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param list<string> $blogIds
     */
    private function createOrder(string $userId, array $blogIds, ?string $promotionReferencedId = null): string
    {
        $id = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();

        $lineItems = [];
        $position = 1;

        foreach ($blogIds as $blogId) {
            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'identifier' => $blogId,
                'referencedId' => $blogId,
                'blogId' => $blogId,
                'type' => LineItem::BLOG_LINE_ITEM_TYPE,
                'quantity' => 1,
                'label' => 'Test blog',
                'good' => true,
                'position' => $position++,
                'payload' => ['blogNumber' => $blogId],
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
            ];
        }

        if ($promotionReferencedId !== null) {
            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'identifier' => 'promotion',
                'referencedId' => $promotionReferencedId,
                'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                'quantity' => 1,
                'label' => 'promotion',
                'position' => $position,
                'price' => new CalculatedPrice(-2, -2, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(-2, new TaxRuleCollection()),
            ];
        }

        static::getContainer()->get('order.repository')->create([[
            'id' => $id,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->getStateMachineState(),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => $this->channelId,
            'billingAddressId' => $billingAddressId,
            'orderNumber' => Uuid::randomHex(),
            'deepLinkCode' => Uuid::randomHex(),
            'addresses' => [[
                'id' => $billingAddressId,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ]],
            'lineItems' => $lineItems,
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ]], Context::createDefaultContext());

        return $id;
    }
}
