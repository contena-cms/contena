<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Event\UserDeletedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class UserBeforeDeleteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testUserDeletedEventDispatched(): void
    {
        $email1 = Uuid::randomHex() . '@contena.cn';
        $email2 = Uuid::randomHex() . '@contena.cn';

        $userId1 = $this->createUser($email1);
        $userId2 = $this->createUser($email2);

        $context = Context::createDefaultContext();

        $caughtEvents = [];

        $listenerClosure = static function (Event $event) use (&$caughtEvents): void {
            $caughtEvents[] = $event;
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(UserDeletedEvent::class, $listenerClosure);

        $this->userRepository->delete([
            ['id' => $userId1],
            ['id' => $userId2],
        ], $context);

        static::assertCount(2, $caughtEvents);

        foreach ($caughtEvents as $event) {
            static::assertInstanceOf(UserDeletedEvent::class, $event);
            static::assertContains($event->getUserId(), [$userId1, $userId2]);
        }
    }

    private function createUser(string $email): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'encryption',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        return $userId;
    }
}
