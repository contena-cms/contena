<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Contena\Core\Framework\Store\Event\ContenaAccountLogoutEvent;
use Contena\Core\Framework\Store\Services\ExtensionLoader;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\Framework\Store\Services\StoreService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(StoreClient::class)]
class StoreClientTest extends TestCase
{
    public function testLogoutRemovesStoreTokenAndDispatchesLogoutEvent(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeService = $this->createMock(StoreService::class);
        $storeService->expects($this->once())
            ->method('removeStoreToken')
            ->with($context);

        $eventDispatcher = new CollectingEventDispatcher();

        $storeClient = new StoreClient(
            [],
            $storeService,
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractStoreRequestOptionsProvider::class),
            static::createStub(ExtensionLoader::class),
            static::createStub(ClientInterface::class),
            static::createStub(InstanceService::class),
            new RequestStack(),
            static::createStub(CacheInterface::class),
            $eventDispatcher,
        );

        $storeClient->logout($context);

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(ContenaAccountLogoutEvent::class, $eventDispatcher->getEvents()[0]);
    }
}
