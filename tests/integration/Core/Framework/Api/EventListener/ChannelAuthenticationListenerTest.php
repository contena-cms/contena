<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\EventListener;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\EventListener\Authentication\ChannelAuthenticationListener;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
class ChannelAuthenticationListenerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const array MAINTENANCE_ALLOWED_IPS = ['192.168.0.2', '192.168.0.1', '192.168.0.3'];

    private ChannelAuthenticationListener $listener;

    private Connection $connection;

    private string $accessKey;

    protected function setUp(): void
    {
        $this->listener = static::getContainer()->get(ChannelAuthenticationListener::class);
        $this->connection = static::getContainer()->get(Connection::class);

        $accessKey = $this->connection->fetchOne(
            'SELECT access_key FROM channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );
        static::assertIsString($accessKey);
        $this->accessKey = $accessKey;
    }

    public function testInactiveChannel(): void
    {
        $this->updateChannel(['active' => false]);

        $this->expectExceptionObject(ApiException::channelNotFound());
        $this->listener->validateRequest($this->createEvent($this->createRequest()));
    }

    public function testActiveChannel(): void
    {
        $request = $this->createRequest();

        $this->listener->validateRequest($this->createEvent($request));

        static::assertSame(TestDefaults::CHANNEL, $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
    }

    public function testMaintenanceChannel(): void
    {
        $this->updateChannel([
            'maintenance' => true,
            'maintenance_ip_allowlist' => Json::encode([]),
        ]);

        $this->expectExceptionObject(ApiException::channelInMaintenanceMode());
        $this->listener->validateRequest($this->createEvent($this->createRequest()));
    }

    public function testMaintenanceChannelAndClientInAllowedIps(): void
    {
        $this->updateChannel([
            'maintenance' => true,
            'maintenance_ip_allowlist' => Json::encode(self::MAINTENANCE_ALLOWED_IPS),
        ]);
        $request = $this->createRequest('192.168.0.1');

        $this->listener->validateRequest($this->createEvent($request));

        static::assertSame(TestDefaults::CHANNEL, $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
    }

    public function testMaintenanceChannelAndClientNotInAllowedIps(): void
    {
        $this->updateChannel([
            'maintenance' => true,
            'maintenance_ip_allowlist' => Json::encode(self::MAINTENANCE_ALLOWED_IPS),
        ]);

        $this->expectExceptionObject(ApiException::channelInMaintenanceMode());
        $this->listener->validateRequest($this->createEvent($this->createRequest('192.168.0.4')));
    }

    public function testMaintenanceChannelWithMaintenanceAllowedRoute(): void
    {
        $this->updateChannel([
            'maintenance' => true,
            'maintenance_ip_allowlist' => Json::encode([]),
        ]);
        $request = $this->createRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE, true);

        $this->listener->validateRequest($this->createEvent($request));

        static::assertSame(TestDefaults::CHANNEL, $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
    }

    public function testRouteWithoutAuthRequiredIgnoresActiveAndMaintenanceFlags(): void
    {
        $this->updateChannel([
            'active' => false,
            'maintenance' => true,
        ]);
        $request = $this->createRequest();
        $request->attributes->set('auth_required', false);
        $request->headers->remove(PlatformRequest::HEADER_ACCESS_KEY);

        $this->listener->validateRequest($this->createEvent($request));

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
    }

    public function testMissingAccessKey(): void
    {
        $request = $this->createRequest();
        $request->headers->remove(PlatformRequest::HEADER_ACCESS_KEY);

        $this->expectExceptionObject(ApiException::unauthorized(
            'header',
            \sprintf('Header "%s" is required.', PlatformRequest::HEADER_ACCESS_KEY),
        ));
        $this->listener->validateRequest($this->createEvent($request));
    }

    public function testNonChannelAccessKey(): void
    {
        $request = $this->createRequest();
        $request->headers->set(PlatformRequest::HEADER_ACCESS_KEY, AccessKeyHelper::generateAccessKey('user'));

        $this->expectExceptionObject(ApiException::channelNotFound());
        $this->listener->validateRequest($this->createEvent($request));
    }

    private function createRequest(string $remoteAddress = '127.0.0.1'): Request
    {
        $request = Request::create(
            '/channel-api/test',
            server: ['REMOTE_ADDR' => $remoteAddress]
        );
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $this->accessKey);

        return $request;
    }

    private function createEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::getContainer()->get('kernel'),
            static fn (): Response => new Response(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    /**
     * @param array<string, bool|string> $data
     */
    private function updateChannel(array $data): void
    {
        foreach ($data as $key => $value) {
            if (\is_bool($value)) {
                $data[$key] = (int) $value;
            }
        }

        $data['updated_at'] = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(
            'channel',
            $data,
            ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );
    }
}
