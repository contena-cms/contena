<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\System\Member\Subscriber\AddressHashSubscriber;

/**
 * @internal
 */
#[CoversClass(AddressHashSubscriber::class)]
class AddressHashSubscriberTest extends TestCase
{
    public function testGenerate(): void
    {
        $addressData = [
            'firstName' => 'address-first-name',
            'lastName' => 'address-last-name',
            'zipcode' => 'address-zipcode',
            'city' => 'address-city',
            'title' => 'address-title',
            'street' => 'address-street',
            'additionalAddressLine1' => 'address-additional-address-line-1',
            'additionalAddressLine2' => 'address-additional-address-line-2',
            'countryId' => 'address-country-id',
            'regionId' => 'address-region-id',
        ];
        $address = new MemberAddressEntity()->assign($addressData);
        $event = new EntityLoadedEvent(
            new MemberAddressDefinition(),
            [$address],
            Context::createDefaultContext(),
        );

        new AddressHashSubscriber()->generateAddressHash($event);

        static::assertSame(Hasher::hash($addressData, 'sha256'), $address->getHash());
    }
}
