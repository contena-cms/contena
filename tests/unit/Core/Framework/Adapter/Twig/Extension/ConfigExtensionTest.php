<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\Extension\ConfigExtension;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(ConfigExtension::class)]
class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsConfigFunction(): void
    {
        $extension = new ConfigExtension(static::createStub(SystemConfigService::class));
        $functions = $extension->getFunctions();

        static::assertCount(1, $functions);

        $names = array_map(static fn (TwigFunction $function): string => $function->getName(), $functions);
        static::assertSame(['config'], $names);
    }

    public function testConfigReadsGlobalSystemConfig(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('my.key')
            ->willReturn('value');

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame('value', $extension->config([], 'my.key'));
    }

    public function testConfigReadsChannelSystemConfig(): void
    {
        $channel = new ChannelEntity();
        $channel->setId('channel-1');
        $channelContext = Generator::generateChannelContext(token: 'token', channel: $channel);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with('my.key', 'channel-1')
            ->willReturn('value');

        static::assertSame('value', new ConfigExtension($systemConfigService)->config([
            'channelContext' => $channelContext,
        ], 'my.key'));
    }
}
