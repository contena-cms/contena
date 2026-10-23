<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct;
use Contena\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 */
#[CoversClass(DecodedPurchaseStruct::class)]
class DecodedPurchaseStructTest extends TestCase
{
    public function testWithAdditionalFieldsAllowed(): void
    {
        $element = [
            'identifier' => 'SwagTest',
            'nextBookingDate' => '2025-12-12',
            'quantity' => 1,
            'sub' => 'sub',
            'test' => 'test',
            'addition' => [
                'test' => 'test',
            ],
        ];

        $dto = ValidatorFactory::create($element, DecodedPurchaseStruct::class, true);

        static::assertInstanceOf(DecodedPurchaseStruct::class, $dto);
    }

    public function testWithAdditionalFieldsNotAllowed(): void
    {
        $element = [
            'identifier' => 'SwagTest',
            'nextBookingDate' => '2025-12-12',
            'quantity' => 1,
            'sub' => 'sub',
            'test' => 'test',
        ];

        static::expectException(FrameworkException::class);

        ValidatorFactory::create($element, DecodedPurchaseStruct::class);
    }
}
