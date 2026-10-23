<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Contena\Tests\Integration\Frontend\Page\FrontendPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ConfirmPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItLoadsTheConfirmPage(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        static::assertCount(FrontendPageTestConstants::AVAILABLE_SHIPPING_METHOD_COUNT, $page->getShippingMethods());
        static::assertCount(FrontendPageTestConstants::AVAILABLE_PAYMENT_METHOD_COUNT, $page->getPaymentMethods());
        static::assertNotCount(0, $page->getPaymentMethods());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItIgnoresUnavailableShippingMethods(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        /** @var EntityRepository<ShippingMethodCollection> $shippingMethodRepository */
        $shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $shippingMethods = $shippingMethodRepository->search(new Criteria(), $context->getContext())->getEntities();

        $updates = [];

        foreach ($shippingMethods as $shippingMethod) {
            $updates[] = [
                'id' => $shippingMethod->getId(),
                'availabilityRule' => [
                    'name' => 'test',
                    'priority' => 0,
                ],
            ];
        }

        $shippingMethodRepository->update($updates, $context->getContext());

        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(0, $page->getShippingMethods());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItIgnoresUnavailablePaymentMethods(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        /** @var EntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        $paymentMethod = $paymentMethodRepository->search($criteria, $context->getContext())->getEntities()->first();
        static::assertNotNull($paymentMethod);

        $paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'availabilityRule' => ['name' => 'invalid', 'priority' => 0]],
            ],
            $context->getContext()
        );

        $event = null;
        $this->catchEvent(CheckoutConfirmPageLoadedEvent::class, $event);

        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(FrontendPageTestConstants::AVAILABLE_PAYMENT_METHOD_COUNT - 1, $page->getPaymentMethods());
        self::assertPageEvent(CheckoutConfirmPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testCartErrorAddedOnInvalidAddress(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithLoggedInUserAndWithNavigation();
        $user = $context->getUser();
        static::assertNotNull($user);
        $activeBillingAddress = $user->getActiveBillingAddress();
        static::assertNotNull($activeBillingAddress);

        $newShippingAddress = clone $activeBillingAddress;

        $activeBillingAddress->setFirstName('');
        $newShippingAddress->setId(Uuid::randomHex());
        $newShippingAddress->setLastName('');
        $user->setActiveShippingAddress($newShippingAddress);

        $cartErrors = $this->getPageLoader()->load($request, $context)->getCart()->getErrors();
        static::assertCount(2, $cartErrors);
        $errors = $cartErrors->getElements();
        static::assertArrayHasKey('billing-address-invalid', $errors);
        static::assertArrayHasKey('shipping-address-invalid', $errors);

        $billingAddressViolation = $errors['billing-address-invalid'];
        static::assertInstanceOf(AddressValidationError::class, $billingAddressViolation);
        $violation = $billingAddressViolation->getViolations()->get(0);
        static::assertSame('/firstName', $violation->getPropertyPath());

        $shippingAddressViolation = $errors['shipping-address-invalid'];
        static::assertInstanceOf(AddressValidationError::class, $shippingAddressViolation);
        $violation = $shippingAddressViolation->getViolations()->get(0);
        static::assertSame('/lastName', $violation->getPropertyPath());
    }

    protected function getPageLoader(): CheckoutConfirmPageLoader
    {
        return static::getContainer()->get(CheckoutConfirmPageLoader::class);
    }
}
