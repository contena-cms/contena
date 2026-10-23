<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Aggregate\UserAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;

/**
 * @internal
 */
#[CoversClass(UserAddressEntity::class)]
class UserAddressEntityTest extends TestCase
{
    #[DataProvider('zipcodeProvider')]
    #[TestDox('setZipcode normalizes empty values to null')]
    public function testSetZipcodeNormalizesEmptyToNull(?string $input, ?string $expected): void
    {
        $address = new UserAddressEntity();
        $address->setZipcode($input);

        static::assertSame($expected, $address->getZipcode());
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: ?string}>
     */
    public static function zipcodeProvider(): \Generator
    {
        yield 'null stays null' => [null, null];
        yield 'empty string becomes null' => ['', null];
        yield 'non-empty value is kept' => ['12345', '12345'];
        yield 'zero string is kept' => ['0', '0'];
    }

    public function testNameGettersAndSetters(): void
    {
        $address = new UserAddressEntity();
        $address->setName('Jane');
        $address->setLastName('Doe');
        $address->setCity('Berlin');
        $address->setStreet('Main Street 1');

        static::assertSame('Jane', $address->getName());
        static::assertSame('Doe', $address->getLastName());
        static::assertSame('Berlin', $address->getCity());
        static::assertSame('Main Street 1', $address->getStreet());
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $address = new UserAddressEntity();

        static::assertNull($address->getCompany());
        static::assertNull($address->getDepartment());
        static::assertNull($address->getPhoneNumber());
        static::assertNull($address->getHash());
    }
}
