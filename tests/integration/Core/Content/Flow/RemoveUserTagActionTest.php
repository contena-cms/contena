<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveUserTagAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class RemoveUserTagActionTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    private Connection $connection;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->flowRepository = static::getContainer()->get('flow.repository');

        $this->connection = static::getContainer()->get(Connection::class);

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    public function testRemoveUserTagAction(): void
    {
        $this->createDataTest();

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $sequenceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $this->flowRepository->create([[
            'name' => 'User login',
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
                    'actionName' => RemoveUserTagAction::getName(),
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id') => 'test tag',
                            $this->ids->get('tag_id2') => 'test tag2',
                        ],
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $sequenceId,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagIds' => [
                            $this->ids->get('tag_id3') => 'test tag3',
                        ],
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $userTag = $this->connection->fetchAllAssociative(
            'SELECT lower(hex(tag_id)) FROM user_tag WHERE user_id = (:userId)',
            ['userId' => Uuid::fromHexToBytes($this->ids->get('user'))]
        );

        static::assertCount(2, $userTag);

        $this->login($email, 'contena');

        $userTag = $this->connection->fetchAllAssociative(
            'SELECT * FROM user_tag WHERE user_id = (:userId)',
            ['userId' => Uuid::fromHexToBytes($this->ids->get('tag_id'))]
        );

        static::assertCount(0, $userTag);
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
                'firstName' => 'Max',
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
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
            'tags' => [
                ['tagId' => $this->ids->get('tag_id'), 'name' => 'tag'],
                ['tagId' => $this->ids->get('tag_id2'), 'name' => 'tag2'],
            ],
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }

    private function createDataTest(): void
    {
        static::getContainer()->get('tag.repository')->create([
            [
                'id' => $this->ids->create('tag_id'),
                'name' => 'test tag',
            ],
            [
                'id' => $this->ids->create('tag_id2'),
                'name' => 'test tag2',
            ],
            [
                'id' => $this->ids->create('tag_id3'),
                'name' => 'test tag3',
            ],
        ], Context::createDefaultContext());
    }
}
