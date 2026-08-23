<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\SymfonySystemConfigService;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(SystemConfigService::class)]
class SystemConfigServiceTest extends TestCase
{
    private Connection&Stub $connection;

    private ConfigReader&Stub $configReader;

    private AbstractSystemConfigLoader&Stub $configLoader;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->configReader = static::createStub(ConfigReader::class);
        $this->configLoader = static::createStub(AbstractSystemConfigLoader::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);

        $this->configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService([]),
            static::createStub(CacheTagCollector::class),
            new NativeClock()
        );
    }

    public function testMultipleChangedEventsFired(): void
    {
        $beforeEventAssert = static function (Event $event): void {
            static::assertInstanceOf(BeforeSystemConfigMultipleChangedEvent::class, $event);
            $event->setValue('foo.bar', 40);
        };

        $eventAssert = static function (Event $event): void {
            static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $event);
            static::assertSame(40, $event->getConfig()['foo.bar']);
        };

        $expects = $this->exactly(6);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($expects)
            ->method('dispatch')
            ->willReturnCallback(static function (Event $event) use ($expects, $beforeEventAssert, $eventAssert) {
                match ($expects->numberOfInvocations()) {
                    1 => $beforeEventAssert($event),
                    6 => $eventAssert($event),
                    default => null,
                };

                return $event;
            });

        $configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $eventDispatcher,
            new SymfonySystemConfigService([]),
            static::createStub(CacheTagCollector::class),
            new NativeClock()
        );

        $configService->setMultiple(['foo.bar' => 'value', 'bar.foo' => 50]);
    }

    public function testNotAllowedToSetKeysManagedBySystem(): void
    {
        $configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService(['default' => ['core.test' => true]]),
            static::createStub(CacheTagCollector::class),
            new NativeClock()
        );

        // Setting the same value is okay
        $configService->set('core.test', true);

        $this->expectExceptionObject(SystemConfigException::systemConfigKeyIsManagedBySystems('core.test'));

        $configService->set('core.test', false);
    }

    public function testGetDomainFiltersOutUnrelatedYamlDefaults(): void
    {
        $queryBuilder = static::createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('addOrderBy')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllNumeric')->willReturn([]);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $configService = new SystemConfigService(
            $this->connection,
            $this->configReader,
            $this->configLoader,
            $this->eventDispatcher,
            new SymfonySystemConfigService(['default' => ['foo.bar.key1' => 'value1', 'baz.qux.key2' => 'value2']]),
            static::createStub(CacheTagCollector::class),
            new NativeClock()
        );

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $configService->getDomain('foo.bar');

        static::assertSame(['foo.bar.key1' => 'value1'], $result);
    }

    public function testGetDomainRejectsEmptyDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain('Empty domain'));

        $this->configService->getDomain('');
    }

    public function testGetDomainRejectsOnlySpacesDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain('Empty domain'));

        $this->configService->getDomain('     ');
    }

    public function testSetRejectsEmptyKey(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidKey('key may not be empty'));

        $this->configService->set('', 'throws error');
    }

    public function testSetRejectsOnlySpacesKey(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidKey('key may not be empty'));

        $this->configService->set('          ', 'throws error');
    }

    public function testSetMultiForwardsSilentToEvent(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (Event $event) use (&$dispatchedEvent) {
                if ($event instanceof SystemConfigMultipleChangedEvent) {
                    $dispatchedEvent = $event;
                }

                return $event;
            });

        $this->configService->setMultiple(['foo.bar' => 'value'], null, true);

        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $dispatchedEvent);
        static::assertTrue($dispatchedEvent->isSilent());
    }

    public function testSetMultiDefaultsSilentToTrue(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (Event $event) use (&$dispatchedEvent) {
                if ($event instanceof SystemConfigMultipleChangedEvent) {
                    $dispatchedEvent = $event;
                }

                return $event;
            });

        $this->configService->setMultiple(['foo.bar' => 'value']);

        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $dispatchedEvent);
        static::assertTrue($dispatchedEvent->isSilent());
    }
}
