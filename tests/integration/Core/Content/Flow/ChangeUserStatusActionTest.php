<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserStatusAction;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ChangeUserStatusActionTest extends TestCase
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

    public function testChangeUserStatusAction(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $sequenceId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();

        $this->flowRepository->create([
            [
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
                        'actionName' => ChangeUserStatusAction::getName(),
                        'config' => [
                            'active' => false,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->login($email, 'contena');

        /** @var UserEntity $user */
        $user = $this->userRepository->search(
            new Criteria([$this->ids->get('user')]),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertFalse($user->getActive());
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
            'active' => true,
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }
}
