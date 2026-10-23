<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Event\UserDeletedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Group('store-api')]
class DeleteUserRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var callable
     */
    private $callbackFn;

    /**
     * @var array<mixed>
     */
    private array $events;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignChannelContext($this->browser);

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->events = [];
    }

    public function testNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testDeleteAValidUser(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserDeletedEvent::class, $this->callbackFn);

        static::assertArrayNotHasKey(
            UserDeletedEvent::class,
            $this->events,
            'IndexStartEvent was dispatched but should not yet.'
        );

        $email = Uuid::randomHex() . '@example.com';
        $id = $this->createUser($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/user',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$id]);
        $user = $this->userRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($user);

        static::assertArrayHasKey(UserDeletedEvent::class, $this->events);
        $userDeletedEvent = $this->events[UserDeletedEvent::class];
        static::assertInstanceOf(UserDeletedEvent::class, $userDeletedEvent);
    }

    public function testDeleteGuestUser(): void
    {
        $userId = $this->createUser(null, true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->getLoggedInContextToken($userId, $this->ids->get('sales-channel')));

        $this->browser
            ->request(
                'DELETE',
                '/store-api/account/user',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria([$userId]);
        $user = $this->userRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNull($user);
    }
}
