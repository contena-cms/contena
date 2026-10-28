<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CustomerNumberRuleTest extends TestCase
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

    private CustomerNumberRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new CustomerNumberRule();
    }

    public function testValidateWithMissingNumbers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyCustomerGroupIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'numbers' => [],
                        'operator' => CustomerNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidCustomerGroupIdsType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'numbers' => '1234',
                        'operator' => CustomerNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exceptions[0]['code']);
        }
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
                'type' => (new CustomerNumberRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'numbers' => ['12345', '23', '42'],
                    'operator' => CustomerNumberRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    /**
     * @param array<string> $customerNumbers
     */
    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, array $customerNumbers, bool $noCustomer = false): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('1337');
        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['numbers' => $customerNumbers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<string, array<string|bool|array<string>>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / match / customer number' => [Rule::OPERATOR_EQ, true, ['1337']];
        yield 'operator_eq / no match / customer number' => [Rule::OPERATOR_EQ, false, ['0000']];
        yield 'operator_eq / no match / empty customer' => [Rule::OPERATOR_EQ, false, ['0000'], true];

        yield 'operator_neq / no match / customer number' => [Rule::OPERATOR_NEQ, false, ['1337']];
        yield 'operator_neq / match / customer number' => [Rule::OPERATOR_NEQ, true, ['0000']];

        yield 'operator_neq / match / empty customer' => [Rule::OPERATOR_NEQ, true, ['0000'], true];
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new CustomerNumberRule();
            $salesChannelContext = static::createStub(SalesChannelContext::class);
            $salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());
            $rule->match(new CheckoutRuleScope($salesChannelContext));
            static::fail('Exception was not thrown');
        } catch (\Throwable $exception) {
            if (Feature::isActive('v6.8.0.0')) {
                static::assertInstanceOf(CustomerException::class, $exception);
                static::assertSame(CustomerException::VALUE_NOT_SUPPORTED, $exception->getErrorCode());
            } else {
                static::assertInstanceOf(UnsupportedValueException::class, $exception);
            }
        }
    }
}
