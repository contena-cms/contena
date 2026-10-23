<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionDiscountZeroValueError;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Contena\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Checkout\Promotion\Rule\PromotionLineItemRule;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionCalculatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private PromotionCalculator $promotionCalculator;

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->promotionCalculator = $container->get(PromotionCalculator::class);
        $this->promotionRepository = $container->get(\sprintf('%s.repository', PromotionDefinition::ENTITY_NAME));

        $channelService = $container->get(ChannelContextService::class);
        $this->channelContext = $channelService->get(
            new ChannelContextServiceParameters(
                TestDefaults::CHANNEL,
                Uuid::randomHex()
            )
        );
    }

    public function testCalculateDoesNotAddDiscountItemsWithoutScope(): void
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());
        $toCalculate = new Cart(Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(0, $toCalculate->getLineItems());
    }

    public function testCalculateDoesNotAddDiscountItemsWithDeliveryScope(): void
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItem->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY);
        $discountItem->setPayloadValue('exclusions', []);
        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());
        $toCalculate = new Cart(Uuid::randomHex());

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(0, $toCalculate->getLineItems());
    }

    public function testCalculateAddsValidPromotionToCalculatedCart(): void
    {
        $promotionId = $this->getPromotionId();
        $discountItem = $this->getDiscountItem($promotionId);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionLineItem = $promotionLineItems->first();

        static::assertNotNull($promotionLineItem);
        static::assertNotNull($promotionLineItem->getPrice());

        static::assertSame(-10.0, $promotionLineItem->getPrice()->getTotalPrice());
    }

    public function testCalculateInformsAboutRedeemedCodeThatGrantsNoDiscount(): void
    {
        $discountItem = $this->getDiscountItem($this->getPromotionId());
        $discountItem->setPayloadValue('code', 'PHPUNIT');
        $discountItem->setPriceDefinition(new AbsolutePriceDefinition(0.0));

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            new Cart(Uuid::randomHex()),
            $toCalculate,
            $this->channelContext,
            new CartBehavior()
        );

        static::assertCount(0, $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE));

        $errors = $toCalculate->getErrors()->filterInstance(PromotionDiscountZeroValueError::class);
        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(PromotionDiscountZeroValueError::class, $error);
        static::assertSame('PHPUnit', $error->getName());
    }

    public function testCalculateWithPreventedCombination(): void
    {
        $nonePreventedPromotionId = $this->getPromotionId();
        $discountItemToBeExcluded = $this->getDiscountItem($nonePreventedPromotionId);

        $preventedPromotionId = $this->getPromotionId(true);
        $validDiscountItem = $this->getDiscountItem($preventedPromotionId);
        $validDiscountItem->setPayloadValue('preventCombination', true);
        $validDiscountItem->setPayloadValue('priority', 2);

        $discountItems = new LineItemCollection([$discountItemToBeExcluded, $validDiscountItem]);

        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        // We expect the blog plus 1 promotion in the cart so overall a count of 2 items
        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        // Make sure that only the expected promotion is in the cart
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-10.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $validDiscountItem->getReferencedId());
    }

    /**
     * A promotion that becomes valid after an earlier promotion is added must still exclude later promotions.
     *
     * @throws CartException
     */
    public function testPromotionDependingOnPromotionInCartExcludesLowerPriorityPromotion(): void
    {
        $blogId = $this->createBlog(gross: 100.0);
        $appliedPromotionId = $this->getPromotionId(priority: 3, value: 10.0);
        $dependentPromotionId = $this->getPromotionId(priority: 2, value: 20.0);
        $excludedPromotionId = $this->getPromotionId(priority: 1, value: 40.0);

        foreach ([$appliedPromotionId, $dependentPromotionId, $excludedPromotionId] as $promotionId) {
            $this->promotionRepository->update([
                ['id' => $promotionId, 'channels' => [['channelId' => TestDefaults::CHANNEL, 'priority' => 1]]],
            ], $this->channelContext->getContext());
        }

        $ruleId = Uuid::randomHex();
        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'Requires applied promotion', 'priority' => 1],
        ], $this->channelContext->getContext());
        static::getContainer()->get('rule_condition.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'ruleId' => $ruleId,
                'type' => (new PromotionLineItemRule())->getName(),
                'value' => ['operator' => Rule::OPERATOR_EQ, 'identifiers' => [$appliedPromotionId]],
            ],
        ], $this->channelContext->getContext());
        $this->promotionRepository->update([
            ['id' => $dependentPromotionId, 'cartRules' => [['id' => $ruleId]], 'exclusionIds' => [$excludedPromotionId]],
        ], $this->channelContext->getContext());

        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew($this->channelContext->getToken());
        $cart = $this->addBlog($blogId, 1, $cart, $cartService, $this->channelContext);
        static::assertSame(100.0, $cart->getPrice()->getTotalPrice());
        $cart = $this->addPromotionCode(\sprintf('phpUnit-%s', $appliedPromotionId), $cart, $cartService, $this->channelContext);
        static::assertSame(90.0, $cart->getPrice()->getTotalPrice());
        $cart = $this->addPromotionCode(\sprintf('phpUnit-%s', $dependentPromotionId), $cart, $cartService, $this->channelContext);
        static::assertSame(70.0, $cart->getPrice()->getTotalPrice());
        $cart = $this->addPromotionCode(\sprintf('phpUnit-%s', $excludedPromotionId), $cart, $cartService, $this->channelContext);

        static::assertSame(70.0, $cart->getPrice()->getTotalPrice());
        static::assertInstanceOf(PromotionExcludedError::class, $cart->getErrors()->get('promotion-excluded'));
    }

    public function testAutomaticExclusionsDontAddError(): void
    {
        $firstPromotionId = $this->getPromotionId(true, 1, false);
        $firstDiscountItem = $this->getDiscountItem($firstPromotionId);
        $firstDiscountItem->setPriceDefinition(new AbsolutePriceDefinition(-20.0));
        $firstDiscountItem->setPayloadValue('preventCombination', true);
        $firstDiscountItem->setPayloadValue('priority', 1);

        $secondPromotionId = $this->getPromotionId(true, 2, false);
        $secondDiscountItem = $this->getDiscountItem($secondPromotionId);
        $secondDiscountItem->setPayloadValue('preventCombination', true);
        $secondDiscountItem->setPayloadValue('priority', 2);

        $discountItems = new LineItemCollection([$firstDiscountItem, $secondDiscountItem]);

        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        // We expect the blog plus 1 promotion in the cart so overall a count of 2 items
        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        // Make sure that only the expected promotion is in the cart
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-10.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $secondDiscountItem->getReferencedId());

        // Switch priorities and make sure that the other promotion is now in the cart
        $firstDiscountItem->setPayloadValue('priority', 2);
        $secondDiscountItem->setPayloadValue('priority', 1);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(2, $toCalculate->getLineItems());

        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionItem = $promotionLineItems->first();
        static::assertNotNull($promotionItem);
        static::assertNotNull($promotionItem->getPrice());
        static::assertSame(-20.0, $promotionItem->getPrice()->getTotalPrice());
        static::assertSame($promotionItem->getReferencedId(), $firstDiscountItem->getReferencedId());

        $cartErrors = $toCalculate->getErrors();
        foreach ($cartErrors as $cartError) {
            static::assertNotInstanceOf(PromotionExcludedError::class, $cartError);
        }
    }

    public function testFixedUnitPricePromotionisNotEligible(): void
    {
        $promotionId = $this->getPromotionId(type: PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem = $this->getDiscountItem($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(8, 8, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(8, 8, 8, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());

        static::assertNotNull($toCalculate->getErrors()->first());
        static::assertSame('Promotion PHPUnit not eligible for cart!', $toCalculate->getErrors()->first()->getMessage());
    }

    public function testFixedUnitPricePromotions(): void
    {
        $promotionId = $this->getPromotionId(type: PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem = $this->getDiscountItem($promotionId, PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $discountItem->setPayloadValue('filter', ['considerAdvancedRules' => true, 'applierKey' => 'ALL']);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(20, 20, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->setPrice(new CartPrice(20, 20, 20, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());

        static::assertNotNull($toCalculate->getErrors()->first());
        static::assertSame('Discount PHPUnit has been added', $toCalculate->getErrors()->first()->getMessage());
        static::assertSame(10.0, $toCalculate->getPrice()->getTotalPrice());
        static::assertCount(2, $toCalculate->getLineItems());
    }

    public function testTest(): void
    {
        $promotionId = Uuid::randomHex();
        $blog1 = $this->createBlog();
        $blog2 = $this->createBlog();
        $blog3 = $this->createBlog();

        /** @var AbstractChannelContextFactory $factory */
        $factory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $factory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $data = [
            'id' => $promotionId,
            'code' => 'Black Friday',
            'useCodes' => true,
            'useSetGroups' => true,
        ];

        $this->createPromotionWithCustomData($data, $this->promotionRepository, $context);
        $this->createSetGroupDiscount($promotionId, 1, static::getContainer(), 50, null, applierKey: '2');
        $this->createSetGroupFixture('COUNT', 2, 'PRICE_ASC', $promotionId, static::getContainer());

        $promotion = new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE, 'Black Friday');

        $lineItem = new LineItem($blog1, LineItem::BLOG_LINE_ITEM_TYPE, $blog1);
        $lineItem->setRemovable(true);

        $lineItem2 = new LineItem($blog2, LineItem::BLOG_LINE_ITEM_TYPE, $blog2);
        $lineItem2->setRemovable(true);

        $lineItem3 = new LineItem($blog3, LineItem::BLOG_LINE_ITEM_TYPE, $blog2);
        $lineItem3->setRemovable(true);

        /** @var CartService $cartService */
        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew('test-token');

        $firstCalculatedCard = $cartService->add($cart, [$lineItem, $lineItem2, $lineItem3, $promotion], $context);
        static::assertNotNull($firstCalculatedCard->getErrors()->first());
        static::assertSame('Discount Black Friday has been added', $firstCalculatedCard->getErrors()->first()->getMessage());
        static::assertCount(4, $firstCalculatedCard->getLineItems());
        static::assertSame(25.0, $firstCalculatedCard->getPrice()->getTotalPrice());

        $firstCalculatedCard->setErrors(new ErrorCollection());

        $secondCalculatedCard = $cartService->remove($firstCalculatedCard, $lineItem->getId(), $context);
        static::assertCount(0, $secondCalculatedCard->getErrors());
        static::assertCount(3, $secondCalculatedCard->getLineItems());
        static::assertSame(15.0, $secondCalculatedCard->getPrice()->getTotalPrice());

        $thirdCalculatedCard = $cartService->remove($secondCalculatedCard, $lineItem2->getId(), $context);
        static::assertNotNull($thirdCalculatedCard->getErrors()->first());
        static::assertSame('Promotion Black Friday not eligible for cart!', $thirdCalculatedCard->getErrors()->first()->getMessage());
        static::assertCount(1, $thirdCalculatedCard->getLineItems());
        static::assertSame(10.0, $thirdCalculatedCard->getPrice()->getTotalPrice());
    }

    public function testCustomLineItemNotStackableBecomesStackable(): void
    {
        $promotionId = $this->getPromotionId();
        $discountItem = $this->getDiscountItem($promotionId);

        $discountItems = new LineItemCollection([$discountItem]);
        $original = new Cart(Uuid::randomHex());

        $blogLineItem = new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE);
        $blogLineItem->setPrice(new CalculatedPrice(90.0, 90.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $blogLineItem->setStackable(true);

        $customLineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);
        $customLineItem->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $customLineItem->setStackable(false);

        $toCalculate = new Cart(Uuid::randomHex());
        $toCalculate->add($blogLineItem);
        $toCalculate->add($customLineItem);
        $toCalculate->setPrice(new CartPrice(84.03, 100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->promotionCalculator->calculate($discountItems, $original, $toCalculate, $this->channelContext, new CartBehavior());
        static::assertCount(3, $toCalculate->getLineItems());
        $promotionLineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);
        static::assertCount(1, $promotionLineItems);

        $promotionLineItem = $promotionLineItems->first();

        static::assertNotNull($promotionLineItem);
        static::assertNotNull($promotionLineItem->getPrice());

        static::assertSame(-10.0, $promotionLineItem->getPrice()->getTotalPrice());

        // Ensure that non-stackable items are still not stackable!
        $customLineItem = $toCalculate->get($customLineItem->getId());
        static::assertNotNull($customLineItem);
        static::assertFalse($customLineItem->isStackable());
    }

    private function getPromotionId(
        bool $preventCombination = false,
        int $priority = 1,
        bool $useCodes = true,
        string $type = PromotionDiscountEntity::TYPE_ABSOLUTE,
        float $value = 10.0
    ): string {
        $promotionId = Uuid::randomHex();

        $promotionData = [
            'id' => $promotionId,
            'active' => true,
            'exclusive' => false,
            'priority' => $priority,
            'code' => \sprintf('phpUnit-%s', $promotionId),
            'useCodes' => $useCodes,
            'useIndividualCodes' => false,
            'useSetGroups' => false,
            'name' => 'PHP Unit promotion',
            'preventCombination' => $preventCombination,
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => $type,
                    'value' => $value,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if (!$useCodes) {
            unset($promotionData['code']);
        }

        $this->promotionRepository->create(
            [
                $promotionData,
            ],
            $this->channelContext->getContext()
        );

        return $promotionId;
    }

    private function getDiscountItem(string $promotionId, string $typen = PromotionDiscountEntity::TYPE_ABSOLUTE): LineItem
    {
        $discountItemToBeExcluded = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $discountItemToBeExcluded->setPayloadValue('discountType', $typen);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel('PHPUnit');
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }

    private function createBlog(float $gross = 10.0): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('blog.repository')
            ->create([
                [
                    'id' => $id,
                    'name' => 'test',
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $gross * 0.7, 'linked' => false],
                    ],
                    'purchasePrices' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                        ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
                    ],
                    'active' => true,
                    'taxId' => $this->getValidTaxId(),
                    'weight' => 100,
                    'height' => 101,
                    'width' => 102,
                    'length' => 103,
                    'visibilities' => [
                        ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                    ],
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'test',
                        ],
                    ],
                ],
            ], Context::createDefaultContext());

        return $id;
    }
}
