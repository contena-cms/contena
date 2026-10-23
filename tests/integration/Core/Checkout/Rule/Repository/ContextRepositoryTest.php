<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Rule\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\OrRule;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Currency\Rule\CurrencyRule;

/**
 * @internal
 */
class ContextRepositoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('rule.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteRuleWithObject(): void
    {
        $id = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $currencyId2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => (new AndRule())->getName(),
                    'children' => [
                        [
                            'type' => (new OrRule())->getName(),
                            'children' => [
                                [
                                    'type' => (new CurrencyRule())->getName(),
                                    'value' => [
                                        'currencyIds' => [
                                            $currencyId,
                                            $currencyId2,
                                        ],
                                        'operator' => CurrencyRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $rules = $this->repository->search(new Criteria([$id]), $this->context)->getEntities();

        $currencyRule = (new CurrencyRule())->assign(['currencyIds' => [$currencyId, $currencyId2]]);

        $entity = $rules->get($id);
        static::assertNotNull($entity);

        static::assertEquals(
            new AndRule([new AndRule([new OrRule([$currencyRule])])]),
            $entity->getPayload()
        );
    }
}
