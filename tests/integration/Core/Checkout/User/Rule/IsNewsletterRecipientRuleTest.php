<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Rule;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Rule\IsNewsletterRecipientRule;
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
class IsNewsletterRecipientRuleTest extends TestCase
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
                'type' => (new IsNewsletterRecipientRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'isNewsletterRecipient' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testExistingNewsletterChannelIdMatchesToTrue(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();
        $user->setNewsletterChannelIds([Uuid::randomHex() => 'foo', Uuid::randomHex() => 'bar']);

        $channelContext->method('getUser')
            ->willReturn($user);
        $channelContext->method('getChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($channelContext);

        static::assertTrue($isCompanyRule->match($scope));
    }

    public function testEmptyNewsletterChannelIdsMatchesToFalse(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();

        $channelContext->method('getUser')
            ->willReturn($user);
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($channelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }

    public function testMissingNewsletterChannelIdMatchesToFalse(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();
        $user->setNewsletterChannelIds([Uuid::randomHex() => 'bar']);

        $channelContext->method('getUser')
            ->willReturn($user);
        $channelContext->method('getChannelId')
            ->willReturn('foo');
        $isCompanyRule = new IsNewsletterRecipientRule(true);

        $scope = new CheckoutRuleScope($channelContext);

        static::assertFalse($isCompanyRule->match($scope));
    }
}
