<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\Action\SetOrderCustomFieldAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SetOrderCustomFieldActionTest extends TestCase
{
    use OrderActionTrait;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    protected function setUp(): void
    {
        $this->flowRepository = static::getContainer()->get('flow.repository');

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    /**
     * @param array<int, mixed>|null $existedData
     * @param array<int, mixed>|null $updateData
     * @param array<int, mixed>|null $expectData
     */
    #[DataProvider('createDataProvider')]
    public function testCreateCustomFieldForOrder(string $option, ?array $existedData, ?array $updateData, ?array $expectData): void
    {
        $customFieldName = 'custom_field_test';
        $entity = 'order';
        $customFieldId = $this->createCustomField($customFieldName, $entity);

        $this->createUserAndLogin();
        $this->createOrder($this->ids->get('user'), ['customFields' => [$customFieldName => $existedData]]);

        $sequenceId = Uuid::randomHex();
        $this->flowRepository->create([[
            'name' => 'Cancel order',
            'eventName' => 'state_enter.order.state.cancelled',
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => SetOrderCustomFieldAction::getName(),
                    'position' => 1,
                    'config' => [
                        'entity' => $entity,
                        'customFieldId' => $customFieldId,
                        'customFieldText' => $customFieldName,
                        'customFieldValue' => $updateData,
                        'customFieldSetId' => null,
                        'customFieldSetText' => null,
                        'option' => $option,
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $this->cancelOrder();

        /** @var OrderEntity $order */
        $order = static::getContainer()->get('order.repository')->search(new Criteria([$this->ids->get('order')]), Context::createDefaultContext())->getEntities()->first();

        $expect = $option === 'clear' ? null : [$customFieldName => $expectData];
        static::assertSame($order->getCustomFields(), $expect);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function createDataProvider(): iterable
    {
        yield 'upsert replaces existing custom field values' => ['upsert', ['red', 'green'], ['blue', 'gray'], ['blue', 'gray']];
        yield 'create leaves existing custom field values unchanged' => ['create', ['red', 'green'], ['blue', 'gray'], ['red', 'green']];
        yield 'clear removes existing custom field values' => ['clear', ['red', 'green', 'blue'], null, null];
        yield 'add appends custom field values' => ['add', ['red', 'green'], ['blue', 'gray'], ['red', 'green', 'blue', 'gray']];
        yield 'remove deletes matching custom field values' => ['remove', ['red', 'green', 'blue'], ['green', 'blue'], ['red']];
    }
}
