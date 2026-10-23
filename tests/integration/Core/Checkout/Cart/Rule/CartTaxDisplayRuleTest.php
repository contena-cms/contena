<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\CartTaxDisplayRule;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class CartTaxDisplayRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new CartTaxDisplayRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'taxDisplay' => 'gross',
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    public function testThatTaxStateMatches(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getTaxState')->willReturn('gross');
        $isGrossRule = new CartTaxDisplayRule('gross');
        $scope = new CheckoutRuleScope($channelContext);

        static::assertTrue($isGrossRule->match($scope));
    }

    public function testThatTaxStateNotMatches(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getTaxState')->willReturn('net');
        $isGrossRule = new CartTaxDisplayRule('gross');
        $scope = new CheckoutRuleScope($channelContext);

        static::assertFalse($isGrossRule->match($scope));
    }
}
