<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Subscriber\AddressHashSubscriber;

/**
 * @internal
 */
#[CoversClass(AddressHashSubscriber::class)]
class AddressHashSubscriberTest extends TestCase
{
    private AddressHashSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new AddressHashSubscriber();
    }

    #[DataProvider('generateProvider')]
    public function testGenerate(UserAddressEntity|OrderAddressEntity $address, string $expectedHash): void
    {
        $event = new EntityLoadedEvent(
            new UserAddressDefinition(),
            [$address],
            Context::createDefaultContext()
        );

        $this->subscriber->generateAddressHash($event);

        static::assertSame($expectedHash, $address->getHash());
    }

    public static function generateProvider(): \Generator
    {
        $address = [
            'name' => 'address-first-name',
            'lastName' => 'address-last-name',
            'zipcode' => 'address-zipcode',
            'city' => 'address-city',
            'company' => 'address-company',
            'department' => 'address-department',
            'title' => 'address-title',
            'street' => 'address-street',
            'additionalAddressLine1' => 'address-additional-address-line-1',
            'additionalAddressLine2' => 'address-additional-address-line-2',
            'countryId' => 'address-country-id',
            'countryStateId' => 'address-country-state-id',
        ];

        yield 'OrderAddressEntity' => [
            (new OrderAddressEntity())->assign($address),
            '949c5f5f8ea7e6b2ff979c8b6d5f54a9c57394d9bc56a3e62a7ecbaa309b1192',
        ];

        yield 'UserAddressEntity' => [
            (new UserAddressEntity())->assign($address),
            '949c5f5f8ea7e6b2ff979c8b6d5f54a9c57394d9bc56a3e62a7ecbaa309b1192',
        ];
    }
}
