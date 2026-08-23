<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SitemapGenerateCommand::class)]
class SitemapGenerateCommandTest extends TestCase
{
    public function testPlatformCommandScansChannelsWithGlobalContext(): void
    {
        $channelProvider = $this->createMock(SitemapChannelProvider::class);
        $channelProvider->expects($this->once())
            ->method('getChannels')
            ->with(static::isInstanceOf(Criteria::class))
            ->willReturn((static function (): \Generator {
                yield from [];
            })());

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (object $event): bool {
                static::assertInstanceOf(SitemapChannelCriteriaEvent::class, $event);

                return $event->getContext()->hasGlobalTenantAccess();
            }))
            ->willReturnArgument(0);

        $command = new SitemapGenerateCommand(
            $channelProvider,
            static::createStub(SitemapExporterInterface::class),
            static::createStub(AbstractChannelContextFactory::class),
            $eventDispatcher,
        );

        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
