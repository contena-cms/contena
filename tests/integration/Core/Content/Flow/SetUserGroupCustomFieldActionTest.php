<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Event\UserGroupRegistrationAccepted;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserGroupCustomFieldAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Content\Test\Flow\OrderActionTrait;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SetUserGroupCustomFieldActionTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CacheTestBehaviour;
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
    }

    /**
     * @param array<int, mixed>|null $existedData
     * @param array<int, mixed>|null $updateData
     * @param array<int, mixed>|null $expectData
     */
    #[DataProvider('createDataProvider')]
    public function testCreateCustomFieldForUserGroup(string $option, ?array $existedData, ?array $updateData, ?array $expectData): void
    {
        $customFieldName = 'custom_field_test';
        $entity = 'user_group';
        $customFieldId = $this->createCustomField($customFieldName, $entity);

        $email = 'thuy@gmail.com';
        $this->prepareUser($email, [
            'requestedGroup' => [
                'id' => $this->ids->create('user_group'),
                'name' => 'foo',
                'customFields' => [$customFieldName => $existedData],
            ],
        ]);

        $sequenceId = Uuid::randomHex();
        $this->flowRepository->create([[
            'name' => 'User group registration accepted',
            'eventName' => UserGroupRegistrationAccepted::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => SetUserGroupCustomFieldAction::getName(),
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

        $browser = $this->createClient();
        $browser->request('POST', '/api/_action/user-group-registration/accept', [
            'userIds' => [$this->ids->get('user')],
        ]);

        /** @var UserGroupEntity $userGroup */
        $userGroup = static::getContainer()->get('user_group.repository')
            ->search(new Criteria([$this->ids->get('user_group')]), Context::createDefaultContext())->getEntities()->first();

        $expect = $option === 'clear' ? null : [$customFieldName => $expectData];
        static::assertSame($userGroup->getCustomFields(), $expect);
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
