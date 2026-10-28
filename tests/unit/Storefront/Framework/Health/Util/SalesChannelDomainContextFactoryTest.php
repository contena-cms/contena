<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Checkout\Cart\RuleLoaderResult;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomainContextFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDomainContextFactory::class)]
class SalesChannelDomainContextFactoryTest extends TestCase
{
    private IdsCollection $ids;


    private SalesChannelContext $context;

    private ?string $contextToken = null;

    /**
     * @var array<string, mixed>
     */
    private array $contextOptions = [];

    private ?string $contextSalesChannelId = null;


    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Generator::generateSalesChannelContext();
        $this->domain = SalesChannelDomain::create(
            $this->ids->get('sales-channel'),
            'http://localhost:8000/en',
            $this->ids->get('domain'),
            $this->ids->get('language'),
            $this->ids->get('currency'),
        );
    }

    /**
     * Language, currency and domain are determined by the URL that is probed, so the lookup context has to
     * describe that domain, not the sales channel defaults.
     */
    public function testTheContextDescribesTheDomain(): void
    {
        $this->createFactory(static::createStub(CartRuleLoader::class))->create($this->domain);

        static::assertSame($this->ids->get('sales-channel'), $this->contextSalesChannelId);
        static::assertSame([
            SalesChannelContextService::DOMAIN_ID => $this->ids->get('domain'),
            SalesChannelContextService::LANGUAGE_ID => $this->ids->get('language'),
            SalesChannelContextService::CURRENCY_ID => $this->ids->get('currency'),
        ], $this->contextOptions);
    }

    /**
     * The probe request detects the rules of an anonymous visitor by calculating a new, empty cart, and
     * extensions restricting visibility by rules read them from the context. Without them the lookup would
     * skip every page that is only visible for a rule matching that visitor.
     */
    public function testTheRulesOfAnAnonymousVisitorAreDetectedInTheContext(): void
    {
        $ruleLoader = $this->createMock(CartRuleLoader::class);
        $ruleLoader->expects($this->once())
            ->method('loadByCart')
            ->willReturnCallback(function (SalesChannelContext $context, Cart $cart, CartBehavior $behavior, bool $isNew): RuleLoaderResult {
                static::assertSame($this->context, $context);
                static::assertSame($this->createdCart, $cart, 'the cart has to be created through the CartFactory');
                static::assertSame($this->contextToken, $cart->getToken());
                static::assertTrue($isNew, 'a new cart has to be matched against all rules');
                static::assertTrue($behavior->hasPermission(CheckoutPermissions::SKIP_CART_PERSISTENCE), 'the cart must not be stored');

                $context->setRuleIds([$this->ids->get('always-valid-rule')]);

                return new RuleLoaderResult($cart, new RuleCollection());
            });

        $context = $this->createFactory($ruleLoader)->create($this->domain);

        static::assertSame($this->context, $context);
        static::assertSame([$this->ids->get('always-valid-rule')], $context->getRuleIds());
    }

}
