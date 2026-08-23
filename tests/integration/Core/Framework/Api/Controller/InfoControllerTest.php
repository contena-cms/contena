<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EnvTestBehaviour;

    public function testGetConfig(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://test-app.url']);

        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/config');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertStringStartsWith('6.8.', $response['version']);
        static::assertSame('https://test-app.url', $response['appUrl']);
        static::assertArrayHasKey('Administration', $response['bundles']);
        static::assertArrayHasKey('Frontend', $response['bundles']);
        static::assertSame(['enableAdminWorker', 'enableNotificationWorker', 'transports'], array_keys($response['adminWorker']));
        static::assertTrue($response['settings']['enableUrlFeature']);
        static::assertFalse($response['settings']['presignedUploadSupported']);
        static::assertArrayHasKey('firstMigrationDate', $response['settings']);
        static::assertTrue($response['settings']['enableHtmlSanitizer']);
        static::assertFalse($response['settings']['disableExtensionManagement']);
        static::assertSame(2, $response['settings']['minSearchTermLength']);
        static::assertContains('pdf', $response['settings']['private_allowed_extensions']);
        static::assertContains('application/pdf', $response['settings']['private_allowed_mime_types_by_extension']['pdf']);
        static::assertArrayNotHasKey('shopId', $response);
        static::assertArrayNotHasKey('inAppPurchases', $response);
        static::assertArrayNotHasKey('appUrlReachable', $response['settings']);
        static::assertArrayNotHasKey('appsRequireAppUrl', $response['settings']);
    }

    public function testGetConfigIncludesMimeTypesForEventAddedPrivateExtensions(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        $listener = static function (MediaFileExtensionWhitelistEvent $event): void {
            $extensions = $event->getWhitelist();
            $extensions[] = 'epub';
            $event->setWhitelist($extensions);
        };

        $eventDispatcher->addListener(MediaFileExtensionWhitelistEvent::class, $listener);

        try {
            $client = $this->getBrowser();
            $client->request(Request::METHOD_GET, '/api/_info/config');

            $content = $client->getResponse()->getContent();
            static::assertNotFalse($content);
            $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

            static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            static::assertContains('epub', $response['settings']['private_allowed_extensions']);
            static::assertSame(['application/epub+zip'], $response['settings']['private_allowed_mime_types_by_extension']['epub']);
        } finally {
            $eventDispatcher->removeListener(MediaFileExtensionWhitelistEvent::class, $listener);
        }
    }

    public function testGetContenaVersion(): void
    {
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/version');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertStringStartsWith('6.8.', $response['version']);
    }

    public function testGetContenaVersionOldVersion(): void
    {
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/v1/_info/version');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertStringStartsWith('6.8.', $response['version']);
    }

    public function testFetchApiRoutes(): void
    {
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/routes');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($routes['endpoints']);
        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }

    public function testFetchMessageStats(): void
    {
        $statsService = static::getContainer()->get(StatsService::class);
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 2))),
        ]));
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 1))),
        ]));

        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/message-stats.json');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $stats = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($stats['enabled']);
        static::assertIsArray($stats['stats']);
        static::assertGreaterThanOrEqual(2, $stats['stats']['totalMessagesProcessed']);
        static::assertIsString($stats['stats']['processedSince']);
        static::assertIsFloat($stats['stats']['averageTimeInQueue']);
        static::assertSame('stdClass', $stats['stats']['messageTypeStats'][0]['type']);
    }
}
