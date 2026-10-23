<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CartRuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('taxConfigProvider')]
    public function testTaxFreeConfig(string $accountType, bool $taxUserConfig, bool $taxBusinessConfig, string $expectedTaxConfig): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $userGroup = new UserGroupEntity();
        $userGroup->setId(Uuid::randomHex());
        $userGroup->setDisplayGross(true);

        $user = new UserEntity();
        $user->setAccountType($accountType);
        $user->setId(Uuid::randomHex());
        $user->setGroup($userGroup);
        $user->setCompany('test-company');
        $user->setVatIds(['DE123456789']);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setCheckVatIdPattern(false);
        $country->setIsEu(false);

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setTaxFreeFrom(0.0);
        $currency->setFactor(1.5);

        $taxUserConfig = new TaxFreeConfig($taxUserConfig);
        $country->setUserTax($taxUserConfig);

        $taxBusinessConfig = new TaxFreeConfig($taxBusinessConfig);
        $country->setCompanyTax($taxBusinessConfig);

        $channelContext = Generator::generateChannelContext(currency: $currency, currentUserGroup: $userGroup, user: $user, country: $country);

        $cart = new Cart('test');

        $result = $cartRuleLoader->loadByCart($channelContext, $cart, new CartBehavior());
        static::assertSame($expectedTaxConfig, $result->getCart()->getPrice()->getTaxStatus());
    }

    public static function taxConfigProvider(): \Generator
    {
        yield 'user account + user tax free => tax-free' => [
            UserEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            false,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + user tax free => no tax-free' => [
            UserEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'user account + business tax free => no tax-free' => [
            UserEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            true,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business tax + business tax free => tax-free' => [
            UserEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'user account + no free tax => no tax-free' => [
            UserEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business account + no free tax => no tax-free' => [
            UserEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'user account + both free tax => tax-free' => [
            UserEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + both free tax => tax-free' => [
            UserEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];
    }

    public function testExistingCartLoadDoesNotRecreateDeletedCart(): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        $cartPersister = static::getContainer()->get(CartPersister::class);

        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('A', LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()))
                ->setLabel('test')
        );

        $context = $this->createChannelContext($cart->getToken());

        $cartPersister->save($cart, $context);

        $loadedCart = $cartPersister->load($cart->getToken(), $context);
        $loadedCart->setErrorHash('outdated');

        $cartPersister->delete($loadedCart->getToken(), $context);

        $result = $cartRuleLoader->loadByCart($context, $loadedCart, new CartBehavior());

        static::assertTrue($result->getCart()->has('A'));

        $count = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM cart WHERE token = :token',
            ['token' => $loadedCart->getToken()]
        );

        static::assertSame('0', (string) $count);
    }

    private function createChannelContext(string $token): ChannelContext
    {
        return static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL);
    }
}
