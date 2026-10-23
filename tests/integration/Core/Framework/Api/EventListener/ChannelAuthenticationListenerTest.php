<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ChannelAuthenticationListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const MAINTENANCE_ALLOWED_IPS = ['192.168.0.2', '192.168.0.1', '192.168.0.3'];

    public function testInactiveChannel(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => false]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_PRECONDITION_FAILED,
            ApiException::channelNotFound()->getErrorCode()
        );
    }

    public function testActiveChannel(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceChannel(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testInactiveAndMaintenanceChannel(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => false, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_PRECONDITION_FAILED,
            ApiException::channelNotFound()->getErrorCode()
        );
    }

    public function testMaintenanceChannelAndClientInAllowedIps(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpAllowlist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default', server: ['REMOTE_ADDR' => '192.168.0.1']);

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceChannelAndClientNotInAllowedIps(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpAllowlist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default', server: ['REMOTE_ADDR' => '192.168.0.4']);

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testMaintenanceChannelWithMaintenanceAllowedRoute(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-allowed');

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceChannelWithMaintenanceDisallowedRoute(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testMaintenanceChannelWithMaintenanceDisallowedRouteAndClientNotInAllowedIps(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpAllowlist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed', server: ['REMOTE_ADDR' => '192.168.0.1']);

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceChannelWithMaintenanceDisallowedRouteAndClientInAllowedIps(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpAllowlist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed', server: ['REMOTE_ADDR' => '192.168.0.4']);

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testRouteWithoutAuthRequiredIgnoresActiveFlag(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => false]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/no-auth-required');

        $this->assertResponseSuccess($browser);
    }

    public function testRouteWithoutAuthRequiredIgnoreMaintenanceModeFlag(): void
    {
        $browser = $this->createChannelBrowser(channelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/no-auth-required');

        $this->assertResponseSuccess($browser);
    }

    private function assertExceptionResponse(KernelBrowser $browser, int $statusCode, string $errorCode): void
    {
        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame($statusCode, $response->getStatusCode(), (string) $response->getContent());

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('errors', $data);
        static::assertCount(1, $data['errors'] ?? []);

        $error = $data['errors'][0];

        static::assertSame((string) $statusCode, $error['status']);
        static::assertSame($errorCode, $error['code']);
    }

    private function assertResponseSuccess(KernelBrowser $browser): void
    {
        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame('', $content);
    }
}
