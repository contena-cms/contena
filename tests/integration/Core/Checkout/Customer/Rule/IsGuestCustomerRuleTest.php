<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Rule\IsGuestCustomerRule;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class IsGuestCustomerRuleTest extends TestCase
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
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new IsGuestCustomerRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'isGuest' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testThatFilledCompanyInformationMatchesToTrue(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setGuest(true);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isGuestCustomerRule = new IsGuestCustomerRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertTrue($isGuestCustomerRule->match($scope));
    }

    public function testThatUnfilledCompanyInformationMatchesToFalse(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setGuest(false);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $isCompanyRule = new IsGuestCustomerRule(true);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }
}
