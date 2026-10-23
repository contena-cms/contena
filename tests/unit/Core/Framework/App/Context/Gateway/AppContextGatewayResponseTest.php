<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Context\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;

/**
 * @internal
 */
#[CoversClass(AppContextGatewayResponse::class)]
class AppContextGatewayResponseTest extends TestCase
{
    public function testResponse(): void
    {
        $commands = [
            ['command' => 'context_register-user', 'payload' => ['billingAddress' => ['salutationId' => 'salutationId', 'name' => 'firstName', 'lastName' => 'lastName', 'street' => 'street', 'zipcode' => 'zipcode', 'city' => 'city', 'countryId' => 'countryId']]],
            ['command' => 'context_change-currency', 'payload' => ['iso' => 'EUR']],
        ];

        $response = new AppContextGatewayResponse($commands);

        static::assertCount(2, $response->getCommands());

        $response->add(['command' => 'context_change-language', 'payload' => ['iso' => 'DE-BY']]);

        static::assertCount(3, $response->getCommands());

        $response->merge([['command' => 'context_change-shipping-address', 'payload' => ['shippingAddressId' => 'shippingAddressId']]]);

        static::assertCount(4, $response->getCommands());

        static::assertSame([
            ['command' => 'context_register-user', 'payload' => ['billingAddress' => ['salutationId' => 'salutationId', 'name' => 'firstName', 'lastName' => 'lastName', 'street' => 'street', 'zipcode' => 'zipcode', 'city' => 'city', 'countryId' => 'countryId']]],
            ['command' => 'context_change-currency', 'payload' => ['iso' => 'EUR']],
            ['command' => 'context_change-language', 'payload' => ['iso' => 'DE-BY']],
            ['command' => 'context_change-shipping-address', 'payload' => ['shippingAddressId' => 'shippingAddressId']],
        ], $response->getCommands());
    }
}
