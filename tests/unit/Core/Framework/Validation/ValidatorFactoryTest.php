<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct;
use Contena\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 */
#[CoversClass(ValidatorFactory::class)]
class ValidatorFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $data = [
            'identifier' => 'some-identifier',
            'nextBookingDate' => '2023-10-10',
            'quantity' => 10,
            'sub' => 'some-sub',
        ];

        $result = ValidatorFactory::create($data, DecodedPurchaseStruct::class);

        static::assertInstanceOf(DecodedPurchaseStruct::class, $result);
        static::assertSame('some-identifier', $result->identifier);
        static::assertSame(10, $result->quantity);
    }
}
