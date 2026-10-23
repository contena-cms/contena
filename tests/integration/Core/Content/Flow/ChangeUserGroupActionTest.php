<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserGroupAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ChangeUserGroupActionTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

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

    public function testChangeUserGroupAction(): void
    {
        $this->createDataTest();

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $sequenceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'Create Order',
            'eventName' => UserLoginEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => $sequenceId,
                    'parentId' => null,
                    'ruleId' => $ruleId,
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $ruleId,
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => ChangeUserGroupAction::getName(),
                    'config' => [
                        'userGroupId' => $this->ids->get('user_group_id'),
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->login($email, 'contena');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Test group'));
        /** @var UserGroupEntity $userGroupId */
        $userGroupId = static::getContainer()->get('user_group.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();

        /** @var UserEntity $user */
        $user = $this->userRepository->search(new Criteria([$this->ids->get('user')]), Context::createDefaultContext())->getEntities()->first();

        static::assertSame($userGroupId->getId(), $user->getGroupId());
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function createUser(?string $email = null): void
    {
        $user = [
            'id' => $this->ids->create('user'),
            'channelId' => $this->ids->get('sales-channel'),
            'defaultShippingAddress' => [
                'id' => $this->ids->create('address'),
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
            ],
            'defaultBillingAddressId' => $this->ids->get('address'),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }

    private function createDataTest(): void
    {
        static::getContainer()->get('user_group.repository')->create([
            [
                'id' => $this->ids->create('user_group_id'),
                'name' => 'Test group',
            ],
        ], Context::createDefaultContext());
    }
}
