<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerBeforeDeleteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->customerRepository = static::getContainer()->get('customer.repository');
    }

    public function testCustomerDeletedEventDispatched(): void
    {
        $email1 = Uuid::randomHex() . '@contena.cn';
        $email2 = Uuid::randomHex() . '@contena.cn';

        $customerId1 = $this->createCustomer($email1);
        $customerId2 = $this->createCustomer($email2);

        $context = Context::createDefaultContext();

        $caughtEvents = [];

        $listenerClosure = static function (Event $event) use (&$caughtEvents): void {
            $caughtEvents[] = $event;
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerDeletedEvent::class, $listenerClosure);

        $this->customerRepository->delete([
            ['id' => $customerId1],
            ['id' => $customerId2],
        ], $context);

        static::assertCount(2, $caughtEvents);

        foreach ($caughtEvents as $event) {
            static::assertInstanceOf(CustomerDeletedEvent::class, $event);
            static::assertContains($event->getCustomerId(), [$customerId1, $customerId2]);
        }
    }

    private function createCustomer(string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
            'customerNumber' => '12345',
        ];

        static::getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
