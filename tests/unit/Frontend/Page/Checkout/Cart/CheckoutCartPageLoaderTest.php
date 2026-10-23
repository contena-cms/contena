<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Gateway\Channel\CheckoutGatewayRouteResponse;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\Channel\CountryRouteResponse;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartGatewayResult;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPage;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutCartPageLoader::class)]
class CheckoutCartPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader($pageLoader)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutCartPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader($pageLoader)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentShippingAndCountryMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
        ]);

        $shippingMethods = new ShippingMethodCollection([
        ]);

        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $cartService = static::createStub(FrontendCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new FrontendCartGatewayResult(new Cart('test'), $response));

        $countryRoute = static::createStub(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->willReturn($countryResponse);

        $page = $this->createLoader(cartService: $cartService, countryRoute: $countryRoute)->load(
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
        static::assertSame($countries, $page->getCountries());
    }

    public function testNoCountrySetIfLoggedIn(): void
    {
        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $countryRoute = static::createStub(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->willReturn($countryResponse);

        $page = $this->createLoader(countryRoute: $countryRoute)->load(
            new Request(),
            $this->getContextWithDummyUser()
        );

        static::assertCount(0, $page->getCountries());
    }



    private function getContextWithDummyUser(): ChannelContext
    {
        $address = (new UserAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]);

        $user = new UserEntity();
        $user->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = static::createStub(ChannelContext::class);
        $context
            ->method('getUser')
            ->willReturn($user);
        $context
            ->method('getToken')
            ->willReturn('token');

        return $context;
    }
}
