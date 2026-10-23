<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\Maintenance\Staging\Handler\StagingSystemConfigHandler;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingSystemConfigHandler::class)]
class StagingSystemConfigHandlerTest extends TestCase
{
    public function testEmptyOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            true,
            [],
            [],
        ));

        static::assertNull($config->get('core.someKey'));
    }

    public function testSetsDefaultConfigOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('info');

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.someKey' => 'someValue',
                    'core.anotherKey' => true,
                ],
            ],
        ));

        static::assertSame('someValue', $config->get('core.someKey'));
        static::assertTrue($config->get('core.anotherKey'));
    }

    public function testSetsChannelConfigOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('info');

        $channelId = 'a1b2c3d4e5f6';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                $channelId => [
                    'core.someKey' => 'channelValue',
                    'core.anotherKey' => 42,
                ],
            ],
        ));

        static::assertSame('channelValue', $config->get('core.someKey', $channelId));
        static::assertSame(42, $config->get('core.anotherKey', $channelId));
    }

    public function testSetsDefaultAndChannelOverrides(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(3))->method('info');

        $channelId = 'a1b2c3d4e5f6';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.someKey' => 'globalValue',
                ],
                $channelId => [
                    'core.someKey' => 'channelValue',
                    'core.anotherKey' => 42,
                ],
            ],
        ));

        static::assertSame('globalValue', $config->get('core.someKey'));
        static::assertSame('channelValue', $config->get('core.someKey', $channelId));
        static::assertSame(42, $config->get('core.anotherKey', $channelId));
    }

    public function testSetsOverridesForMultipleChannels(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingSystemConfigHandler($config);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->exactly(4))->method('info');

        $channelOne = 'a1b2c3d4e5f6';
        $channelTwo = 'f6e5d4c3b2a1';

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            $io,
            true,
            [],
            [],
            [
                'default' => [
                    'core.sharedKey' => 'global',
                ],
                $channelOne => [
                    'core.sharedKey' => 'channel-one-value',
                    'core.uniqueKey' => 'only-channel-one',
                ],
                $channelTwo => [
                    'core.sharedKey' => 'channel-two-value',
                ],
            ],
        ));

        static::assertSame('global', $config->get('core.sharedKey'));
        static::assertSame('channel-one-value', $config->get('core.sharedKey', $channelOne));
        static::assertSame('only-channel-one', $config->get('core.uniqueKey', $channelOne));
        static::assertSame('channel-two-value', $config->get('core.sharedKey', $channelTwo));
        static::assertNull($config->get('core.uniqueKey', $channelTwo));
    }
}
