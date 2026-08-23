<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Contena\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Contena\Core\System\SystemConfig\SymfonySystemConfigService;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class SystemConfigServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemConfigService = new SystemConfigService(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(ConfigReader::class),
            static::getContainer()->get(SystemConfigLoader::class),
            static::getContainer()->get('event_dispatcher'),
            new SymfonySystemConfigService([]),
            static::getContainer()->get(CacheTagCollector::class),
            new NativeClock()
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function differentTypesProvider(): iterable
    {
        yield 'boolean true value is stored unchanged' => [true];
        yield 'boolean false value is stored unchanged' => [false];
        yield 'null value is stored unchanged' => [null];
        yield 'zero integer value is stored unchanged' => [0];
        yield 'positive integer value is stored unchanged' => [1234];
        yield 'float value is stored unchanged' => [1243.42314];
        yield 'empty string value is stored unchanged' => [''];
        yield 'string value is stored unchanged' => ['test'];
        yield 'array value is stored unchanged' => [['foo' => 'bar']];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $expected
     */
    #[DataProvider('differentTypesProvider')]
    public function testSetGetDifferentTypes(array|float|bool|int|string|null $expected): void
    {
        $this->systemConfigService->set('foo.bar', $expected);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function getStringProvider(): iterable
    {
        yield 'true value is read as string one' => [true, '1'];
        yield 'false value is read as empty string' => [false, ''];
        yield 'null value is read as empty string' => [null, ''];
        yield 'zero integer is read as string zero' => [0, '0'];
        yield 'positive integer is read as string integer' => [1234, '1234'];
        yield 'float value is read as string float' => [1243.42314, '1243.42314'];
        yield 'empty string is read unchanged' => ['', ''];
        yield 'string value is read unchanged' => ['test', 'test'];
        yield 'array value is invalid for string reads' => [['foo' => 'bar'], ''];
    }

    /**
     * @param array<mixed>|bool|int|float|string|null $writtenValue
     */
    #[DataProvider('getStringProvider')]
    public function testGetString($writtenValue, string $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'string', 'array'));
        }
        $actual = $this->systemConfigService->getString('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function getIntProvider(): iterable
    {
        yield 'true value is read as integer one' => [true, 1];
        yield 'false value is read as integer zero' => [false, 0];
        yield 'null value is read as integer zero' => [null, 0];
        yield 'zero integer is read unchanged' => [0, 0];
        yield 'positive integer is read unchanged' => [1234, 1234];
        yield 'float value is truncated to integer' => [1243.42314, 1243];
        yield 'empty string is read as integer zero' => ['', 0];
        yield 'non numeric string is read as integer zero' => ['test', 0];
        yield 'array value is invalid for integer reads' => [['foo' => 'bar'], 0];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getIntProvider')]
    public function testGetInt(array|float|bool|int|string|null $writtenValue, int $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'int', 'array'));
        }
        $actual = $this->systemConfigService->getInt('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function getFloatProvider(): iterable
    {
        yield 'true value is read as float one' => [true, 1];
        yield 'false value is read as float zero' => [false, 0];
        yield 'null value is read as float zero' => [null, 0];
        yield 'zero integer is read as float zero' => [0, 0];
        yield 'positive integer is read as float value' => [1234, 1234];
        yield 'float value is read unchanged' => [1243.42314, 1243.42314];
        yield 'empty string is read as float zero' => ['', 0];
        yield 'non numeric string is read as float zero' => ['test', 0];
        yield 'array value is invalid for float reads' => [['foo' => 'bar'], 0];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getFloatProvider')]
    public function testGetFloat(array|float|bool|int|string|null $writtenValue, float $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        if (\is_array($writtenValue)) {
            $this->expectExceptionObject(SystemConfigException::invalidSettingValueException('foo.bar', 'float', 'array'));
        }
        $actual = $this->systemConfigService->getFloat('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function getBoolProvider(): iterable
    {
        yield 'true value is read as true' => [true, true];
        yield 'false value is read as false' => [false, false];
        yield 'null value is read as false' => [null, false];
        yield 'zero integer is read as false' => [0, false];
        yield 'positive integer is read as true' => [1234, true];
        yield 'float value is read as true' => [1243.42314, true];
        yield 'empty string is read as false' => ['', false];
        yield 'non empty string is read as true' => ['test', true];
        yield 'non empty array value is read as true' => [['foo' => 'bar'], true];
        yield 'array value is read as false' => [[], false];
    }

    /**
     * @param float|bool|int|string|array<mixed>|null $writtenValue
     */
    #[DataProvider('getBoolProvider')]
    public function testGetBool(array|float|bool|int|string|null $writtenValue, bool $expected): void
    {
        $this->systemConfigService->set('foo.bar', $writtenValue);
        $actual = $this->systemConfigService->getBool('foo.bar');
        static::assertSame($expected, $actual);
    }

    /**
     * mysql 5.7.30 casts 0.0 to 0
     */
    public function testFloatZero(): void
    {
        $this->systemConfigService->set('foo.bar', 0.0);
        $actual = $this->systemConfigService->get('foo.bar');
        static::assertSame(0.0, $actual);
    }

    public function testSetGetChannel(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        static::assertSame('test', $this->systemConfigService->get('foo.bar', TestDefaults::CHANNEL));

        $this->systemConfigService->set('foo.bar', 'override', TestDefaults::CHANNEL);
        static::assertSame('override', $this->systemConfigService->get('foo.bar', TestDefaults::CHANNEL));

        $this->systemConfigService->set('foo.bar', '', TestDefaults::CHANNEL);
        static::assertSame('', $this->systemConfigService->get('foo.bar', TestDefaults::CHANNEL));
    }

    public function testSetGetChannelBool(): void
    {
        $this->systemConfigService->set('foo.bar', false);
        static::assertFalse($this->systemConfigService->get('foo.bar', TestDefaults::CHANNEL));

        $this->systemConfigService->set('foo.bar', true, TestDefaults::CHANNEL);
        static::assertTrue($this->systemConfigService->get('foo.bar', TestDefaults::CHANNEL));
    }

    public function testGetDomainNoData(): void
    {
        static::assertSame([], $this->systemConfigService->getDomain('foo'));
        static::assertSame([], $this->systemConfigService->getDomain('foo', null, true));
        static::assertSame([], $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL));
        static::assertSame([], $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL, true));
    }

    public function testGetDomain(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $this->systemConfigService->set('foo.b', 'b');
        $this->systemConfigService->set('foo.c', 'c');
        $this->systemConfigService->set('foo.c', 'c override', TestDefaults::CHANNEL);

        $expected = [
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c',
        ];
        $actual = $this->systemConfigService->getDomain('foo');
        static::assertSame($expected, $actual);

        static::assertSame([
            'foo.a' => 'a',
            'foo.b' => 'b',
            'foo.c' => 'c override',
        ], $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL, true));
        static::assertSame([
            'foo.c' => 'c override',
        ], $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL));
    }

    public function testGetDomainInheritanceIgnoresEmptyChannelOverride(): void
    {
        $this->systemConfigService->set('foo.bar', 'test');
        $this->systemConfigService->set('foo.bar', '', TestDefaults::CHANNEL);

        static::assertSame(
            ['foo.bar' => 'test'],
            $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL, true)
        );
    }

    public function testGetDomainInheritanceKeepsBooleanChannelOverride(): void
    {
        $this->systemConfigService->set('foo.bar', true);
        static::assertSame(
            ['foo.bar' => true],
            $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL, true)
        );

        $this->systemConfigService->set('foo.bar', false, TestDefaults::CHANNEL);
        static::assertSame(
            ['foo.bar' => false],
            $this->systemConfigService->getDomain('foo', TestDefaults::CHANNEL, true)
        );
    }

    public function testGetDomainWithDots(): void
    {
        $this->systemConfigService->set('foo.a', 'a');
        $actual = $this->systemConfigService->getDomain('foo.');
        static::assertSame(['foo.a' => 'a'], $actual);
    }

    public function testDeleteNonExisting(): void
    {
        $this->systemConfigService->delete('not.found');
        $actual = $this->systemConfigService->get('not.found');
        static::assertNull($actual);

        $this->systemConfigService->delete('not.found', TestDefaults::CHANNEL);
        static::assertNull($this->systemConfigService->get('not.found', TestDefaults::CHANNEL));
    }

    public function testDelete(): void
    {
        $this->systemConfigService->set('foo', 'bar');
        $this->systemConfigService->set('foo', 'bar override', TestDefaults::CHANNEL);
        $this->systemConfigService->delete('foo');
        $actual = $this->systemConfigService->get('foo');
        static::assertNull($actual);
        static::assertSame('bar override', $this->systemConfigService->get('foo', TestDefaults::CHANNEL));

        $this->systemConfigService->delete('foo', TestDefaults::CHANNEL);
        static::assertNull($this->systemConfigService->get('foo', TestDefaults::CHANNEL));
    }

    public function testDeleteExtensionConfigurationDeletesAcrossAllChannels(): void
    {
        $extensionName = 'CtTest';
        $configKey1 = $extensionName . '.config.testSetting1';
        $configKey2 = $extensionName . '.config.testSetting2';

        // Create three records, two global and one channel specific.
        $this->systemConfigService->set($configKey1, 'global_value');
        $this->systemConfigService->set($configKey1, 'channel_value', TestDefaults::CHANNEL);
        $this->systemConfigService->set($configKey2, true);

        // Verify that the records exist
        static::assertSame('global_value', $this->systemConfigService->get($configKey1));
        static::assertSame('channel_value', $this->systemConfigService->get($configKey1, TestDefaults::CHANNEL));
        static::assertTrue($this->systemConfigService->getBool($configKey2));
        static::assertTrue($this->systemConfigService->getBool($configKey2, TestDefaults::CHANNEL));

        // Add event listeners to capture dispatched events, structured by scope.
        $dispatchedEvents = [];
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = static function (
            BeforeSystemConfigMultipleChangedEvent|SystemConfigMultipleChangedEvent $event
        ) use (&$dispatchedEvents): void {
            $scope = $event->getChannelId() === null ? 'global' : 'channel';
            $dispatchedEvents[$event::class][$scope][] = $event;
        };

        $this->addEventListener($eventDispatcher, BeforeSystemConfigMultipleChangedEvent::class, $listener);
        $this->addEventListener($eventDispatcher, SystemConfigMultipleChangedEvent::class, $listener);

        $this->systemConfigService->deleteExtensionConfiguration($extensionName, [
            ['elements' => [['name' => 'testSetting1'], ['name' => 'testSetting2']]],
        ]);

        // Reset the memoized values
        $this->getContainer()->get(MemoizedSystemConfigStore::class)->reset();

        // All records should be deleted
        static::assertNull($this->systemConfigService->get($configKey1));
        static::assertNull($this->systemConfigService->get($configKey1, TestDefaults::CHANNEL));
        static::assertFalse($this->systemConfigService->getBool($configKey2));
        static::assertFalse($this->systemConfigService->getBool($configKey2, TestDefaults::CHANNEL));

        // Assert that the events were dispatched correctly for both scopes.
        static::assertCount(1, $dispatchedEvents[BeforeSystemConfigMultipleChangedEvent::class]['global']);
        static::assertCount(1, $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['global']);
        static::assertCount(1, $dispatchedEvents[BeforeSystemConfigMultipleChangedEvent::class]['channel']);
        static::assertCount(1, $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['channel']);

        // Assert content of bulk events
        $globalMultipleEvent = $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['global'][0];
        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $globalMultipleEvent);
        static::assertEquals([$configKey1, $configKey2], array_keys($globalMultipleEvent->getConfig()));

        $channelMultipleEvent = $dispatchedEvents[SystemConfigMultipleChangedEvent::class]['channel'][0];
        static::assertInstanceOf(SystemConfigMultipleChangedEvent::class, $channelMultipleEvent);
        static::assertEquals([$configKey1, $configKey2], array_keys($channelMultipleEvent->getConfig()));
    }
}
