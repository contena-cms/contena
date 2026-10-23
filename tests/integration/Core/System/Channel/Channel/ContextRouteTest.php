<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Group('store-api')]
class ContextRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testFetchingContext(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('channel', $response);
        static::assertSame($response['channel']['id'], $this->ids->get('sales-channel'));
        static::assertSame($response['token'], $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testFetchingContextWithUser(): void
    {
        $this->login($this->browser);

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('channel', $response);
        static::assertSame($response['channel']['id'], $this->ids->get('sales-channel'));

        static::assertIsArray($response);
        static::assertArrayHasKey('user', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['user']);
        static::assertArrayHasKey('activeShippingAddress', $response['user']);
    }

    public function testFetchingContextAfterBillingAddressChange(): void
    {
        $userId = $this->login($this->browser);

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('user', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['user']);

        $newBillingAddressId = Uuid::randomHex();
        $addressRepository = static::getContainer()->get('user_address.repository');
        $addressRepository->create([
            [
                'id' => $newBillingAddressId,
                'userId' => $userId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/context',
                [
                    'billingAddressId' => $newBillingAddressId,
                ]
            );

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('user', $response);
        static::assertArrayHasKey('activeBillingAddress', $response['user']);
        static::assertArrayHasKey('id', $response['user']['activeBillingAddress']);
        static::assertSame($newBillingAddressId, $response['user']['activeBillingAddress']['id']);
    }

    public function testFetchingContextReturnsMeasurementSystem(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('measurementSystem', $response);
        static::assertIsArray($response['measurementSystem']);
        static::assertArrayHasKey('units', $response['measurementSystem']);
        static::assertIsArray($response['measurementSystem']['units']);
        static::assertArrayHasKey('length', $response['measurementSystem']['units']);
        static::assertArrayHasKey('weight', $response['measurementSystem']['units']);
        static::assertSame('mm', $response['measurementSystem']['units']['length']);
        static::assertSame('kg', $response['measurementSystem']['units']['weight']);

        // update measurement system of the sales channel and check again
        $measurementSystem = [
            'system' => 'imperial',
            'units' => [
                'length' => 'in',
                'weight' => 'lb',
            ],
        ];

        $channelRepository = static::getContainer()->get('channel.repository');

        $channelRepository->update([
            [
                'id' => $this->ids->get('sales-channel'),
                'measurementUnits' => $measurementSystem,
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/context'
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('measurementSystem', $response);
        static::assertIsArray($response['measurementSystem']);
        static::assertArrayHasKey('units', $response['measurementSystem']);
        static::assertIsArray($response['measurementSystem']['units']);
        static::assertArrayHasKey('length', $response['measurementSystem']['units']);
        static::assertArrayHasKey('weight', $response['measurementSystem']['units']);
        static::assertSame('in', $response['measurementSystem']['units']['length']);
        static::assertSame('lb', $response['measurementSystem']['units']['weight']);
    }
}
