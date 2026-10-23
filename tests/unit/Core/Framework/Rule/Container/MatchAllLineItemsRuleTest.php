<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Rule\CartRuleScope;
use Contena\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Contena\Core\Checkout\Cart\Rule\LineItemScope;
use Contena\Core\Checkout\Cart\Rule\LineItemTagRule;
use Contena\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Checkout\CartRuleFixture;
use Contena\Core\Test\Stub\Rule\CountingTrueRule;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(MatchAllLineItemsRule::class)]
class MatchAllLineItemsRuleTest extends TestCase
{
    public function testAndRuleNameIsStillTheSame(): void
    {
        static::assertSame('allLineItemsContainer', (new MatchAllLineItemsRule())->getName());
    }

    /**
     * @param array<string> $categoryIdsBlogA
     * @param array<string> $categoryIdsBlogB
     * @param array<string> $categoryIds
     */
    #[DataProvider('getCartScopeTestData')]
    public function testIfMatchesAllCorrectWithCartScope(
        array $categoryIdsBlogA,
        array $categoryIdsBlogB,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule();
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsBlogA),
            $this->createLineItemWithCategories($categoryIdsBlogB),
        ]);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            static::createStub(ChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getCartScopeTestData(): array
    {
        return [
            'all blogs / equal / match category id' => [['1', '2'], ['1', '3'], Rule::OPERATOR_EQ, ['1'], true],
            'all blogs / equal / no match category id' => [['1', '2'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'all blogs / not equal / match category id' => [['2', '3'], ['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'all blogs / not equal / no match category id' => [['2', '3'], ['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'all blogs / empty / match category id' => [[], [], Rule::OPERATOR_EMPTY, [], true],
            'all blogs / empty / no match category id' => [[], ['1', '2'], Rule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @param array<string> $categoryIdsBlog
     * @param array<string> $categoryIds
     */
    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesAllCorrectWithLineItemScope(
        array $categoryIdsBlog,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule();
        $allLineItemsRule->addRule($lineItemRule);

        $match = $allLineItemsRule->match(new LineItemScope(
            $this->createLineItemWithCategories($categoryIdsBlog),
            static::createStub(ChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'blog / equal / match category id' => [['1', '2'], Rule::OPERATOR_EQ, ['1'], true],
            'blog / equal / no match category id' => [['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'blog / not equal / match category id' => [['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'blog / not equal / no match category id' => [['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'blog / empty / match category id' => [[], Rule::OPERATOR_EMPTY, [], true],
        ];
    }

    /**
     * @param array<string> $categoryIdsBlogA
     * @param array<string> $categoryIdsBlogB
     * @param array<string> $categoryIdsBlogC
     * @param array<string> $categoryIds
     */
    #[DataProvider('getCartScopeTestMinimumShouldMatchData')]
    public function testIfMatchesMinimumCorrectWithCartScope(
        array $categoryIdsBlogA,
        array $categoryIdsBlogB,
        array $categoryIdsBlogC,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule([], null, ['blog']);
        $allLineItemsRule->assign(['minimumShouldMatch' => 2]);
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsBlogA),
            $this->createLineItemWithCategories($categoryIdsBlogB),
            $this->createLineItemWithCategories($categoryIdsBlogC),
        ]);

        $promotionLineItem = CartRuleFixture::createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO')->setPayloadValue('promotionId', 'A');
        $lineItemCollection->add($promotionLineItem);

        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            static::createStub(ChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getCartScopeTestMinimumShouldMatchData(): array
    {
        return [
            'minimum 2 blogs / equal / match category id' => [['1', '2'], ['1', '3'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], true],
            'minimum 2 blogs / equal / no match category id' => [['1', '2'], ['2', '3'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'minimum 2 blogs / not equal / match category id' => [['2', '3'], ['2', '3'], ['1', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'minimum 2 blogs / not equal / no match category id' => [['2', '3'], ['1', '2'], ['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'minimum 2 blogs / empty / match category id' => [[], [], [], Rule::OPERATOR_EMPTY, [], true],
            'minimum 2 blogs / empty / no match category id' => [[], ['1', '2'], ['2', '3'], Rule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @param array<string> $categoryIdsBlog
     * @param array<string> $categoryIds
     */
    #[DataProvider('getLineItemScopeTestMinimumShouldMatchData')]
    public function testIfMatchesMinimumCorrectWithLineItemScope(
        array $categoryIdsBlog,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule([], null, ['blog']);
        $allLineItemsRule->assign(['minimumShouldMatch' => 1]);
        $allLineItemsRule->addRule($lineItemRule);

        $match = $allLineItemsRule->match(new LineItemScope(
            $this->createLineItemWithCategories($categoryIdsBlog),
            static::createStub(ChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestMinimumShouldMatchData(): array
    {
        return [
            'minimum 1 blogs / equal / match category id' => [['1', '2'], Rule::OPERATOR_EQ, ['1'], true],
            'minimum 1 blogs / equal / no match category id' => [['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'minimum 1 blogs / not equal / match category id' => [['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'minimum 1 blogs / not equal / no match category id' => [['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'minimum 1 blogs / empty / match category id' => [[], Rule::OPERATOR_EMPTY, [], true],
        ];
    }

    public function testReturnsFalseIfNoLineItemsArePresent(): void
    {
        $rule = new MatchAllLineItemsRule();

        $match = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection()),
            static::createStub(ChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testReturnsFalseWhenScopeIsNotCartOrLineItemScope(): void
    {
        $rule = new MatchAllLineItemsRule();

        $match = $rule->match(static::createStub(RuleScope::class));

        static::assertFalse($match);
    }

    public function testReturnsTrueWhenNoLineItemsOfFilteredTypeExist(): void
    {
        $rule = new MatchAllLineItemsRule([], null, ['blog']);

        $match = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([
                CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            ])),
            static::createStub(ChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testEvaluatesAllItemsWhenNoTypesSet(): void
    {
        $condition = new CountingTrueRule();

        $rule = new MatchAllLineItemsRule([$condition], null, null);

        $collection = new LineItemCollection([
            CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            CartRuleFixture::createLineItem(LineItem::DISCOUNT_LINE_ITEM, 1, 'DISCOUNT'),
            CartRuleFixture::createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, 1, 'BLOG'),
            CartRuleFixture::createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, 1, 'BLOG'),
        ]);

        $match = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart($collection),
            static::createStub(ChannelContext::class)
        ));

        static::assertTrue($match);
        static::assertSame(4, $condition->matchCount);
    }

    public function testFiltersItemsByGivenTypes(): void
    {
        $condition = new CountingTrueRule();

        $rule = new MatchAllLineItemsRule([$condition], null, ['discount', 'custom']);

        $collection = new LineItemCollection([
            CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            CartRuleFixture::createLineItem(LineItem::DISCOUNT_LINE_ITEM, 1, 'DISCOUNT'),
            CartRuleFixture::createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, 1, 'BLOG'),
        ]);

        $match = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart($collection),
            static::createStub(ChannelContext::class)
        ));

        static::assertTrue($match);
        static::assertSame(2, $condition->matchCount);
    }

    public function testCustomLineItemPassesNegatedTagRuleForBlogAndCustomTypes(): void
    {
        $rule = new MatchAllLineItemsRule(
            [new LineItemTagRule(Rule::OPERATOR_NEQ, [Uuid::randomHex()])],
            null,
            [LineItem::BLOG_LINE_ITEM_TYPE, LineItem::CUSTOM_LINE_ITEM_TYPE]
        );

        $match = $rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([
                CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            ])),
            static::createStub(ChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testRuleConstraints(): void
    {
        $rule = new MatchAllLineItemsRule();

        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('minimumShouldMatch', $constraints);
        static::assertArrayHasKey('types', $constraints);

        static::assertCount(1, $constraints['minimumShouldMatch']);
        static::assertCount(1, $constraints['types']);

        static::assertInstanceOf(Type::class, $constraints['minimumShouldMatch'][0]);
        static::assertInstanceOf(Type::class, $constraints['types'][0]);
    }

}
