<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Order;

use Faker\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\CartSerializationCleaner;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Cart\Order\OrderPersister;
use Contena\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Contena\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class OrderPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private OrderPersisterInterface $orderPersister;

    private Processor $cartProcessor;

    private OrderConverter $orderConverter;

    private CartSerializationCleaner $serializationCleaner;

    protected function setUp(): void
    {
        $this->orderPersister = static::getContainer()->get(OrderPersister::class);
        $this->cartProcessor = static::getContainer()->get(Processor::class);
        $this->orderConverter = static::getContainer()->get(OrderConverter::class);
        $this->serializationCleaner = static::getContainer()->get(CartSerializationCleaner::class);
    }

    public function testSave(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        )->add(
            (new LineItem('test2', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test2')
        );
        $positionByIdentifier = [
            'test' => 1,
            'test2' => 2,
        ];

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static function (array $payload) use ($positionByIdentifier) {
                    foreach ($payload[0]['lineItems'] as $lineItem) {
                        if ($positionByIdentifier[$lineItem['identifier']] !== $lineItem['position']) {
                            return false;
                        }
                    }

                    return true;
                })
            );
        $order = new OrderEntity();
        $order->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'order',
                1,
                new EntityCollection([$order]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $persister = new OrderPersister($repository, $this->orderConverter, $this->serializationCleaner);

        $persister->persist($cart, $this->getChannelContext());
    }

    public function testSaveWithMissingLabel(): void
    {
        $cart = new Cart('a-b-c');
        $cart->add(
            (new LineItem('test', LineItem::CREDIT_LINE_ITEM_TYPE))
                ->setPriceDefinition(new AbsolutePriceDefinition(1))
        );

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $processedCart = $this->cartProcessor->process($cart, $context, new CartBehavior());

        $exception = null;

        try {
            $this->orderPersister->persist($processedCart, $context);
        } catch (CartException $exception) {
        }

        static::assertInstanceOf(CartException::class, $exception);
        static::assertStringContainsString('Line item "test" incomplete. Property "label" missing.', $exception->getMessage());
    }

    private function getUser(): UserEntity
    {
        $faker = Factory::create();

        $billingAddress = new UserAddressEntity();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutationId($this->getValidSalutationId());
        $billingAddress->setFirstName($faker->firstName());
        $billingAddress->setLastName($faker->lastName());
        $billingAddress->setStreet($faker->streetAddress());
        $billingAddress->setZipcode($faker->postcode());
        $billingAddress->setCity($faker->city());
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $user = new UserEntity();
        $user->setId('SWAG-CUSTOMER-ID-1');
        $user->setDefaultBillingAddress($billingAddress);
        $user->setEmail('test@example.com');
        $user->setSalutationId($this->getValidSalutationId());
        $user->setFirstName($faker->firstName());
        $user->setLastName($faker->lastName());
        $user->setUserNumber('Test');

        return $user;
    }

    private function getChannelContext(): MockObject&ChannelContext
    {
        $user = $this->getUser();
        $channel = new ChannelEntity();
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->method('getUser')->willReturn($user);

        $context = Context::createDefaultContext();
        $channel->setId(TestDefaults::CHANNEL);
        $channelContext->method('getChannel')->willReturn($channel);
        $channelContext->method('getContext')->willReturn($context);

        return $channelContext;
    }
}
