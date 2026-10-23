<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\Maintenance\Staging\Handler\StagingChannelHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingChannelHandler::class)]
class StagingChannelHandlerTest extends TestCase
{
    public function testReplaceByEqual(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'http://localhost'],
            ]);

        $connection
            ->expects($this->once())
            ->method('update')->with(
                'channel_domain',
                ['url' => 'http://staging.local'],
                ['id' => 'id1']
            );

        $handler = new StagingChannelHandler($connection);

        $domainMapping = [
            ['match' => 'http://localhost', 'type' => 'equal', 'replace' => 'http://staging.local'],
        ];

        $event = new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            $domainMapping
        );

        $handler($event);
    }

    public function testReplaceEqualNoMatch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'http://localhost'],
            ]);

        $connection
            ->expects($this->never())
            ->method('update');

        $handler = new StagingChannelHandler($connection);

        $domainMapping = [
            ['match' => 'http://fooo', 'type' => 'equal', 'replace' => 'http://staging.local'],
        ];

        $event = new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            $domainMapping
        );

        $handler($event);
    }

    public function testReplaceByRegexp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'https://pikachu.com'],
            ]);

        $connection
            ->expects($this->once())
            ->method('update')->with(
                'channel_domain',
                ['url' => 'http://pikachu-com.local'],
                ['id' => 'id1']
            );

        $handler = new StagingChannelHandler($connection);

        $domainMapping = [
            ['match' => '/https?:\/\/(\w+)\.(\w+)$/m', 'type' => 'regex', 'replace' => 'http://$1-$2.local'],
        ];

        $event = new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            $domainMapping
        );

        $handler($event);
    }

    public function testReplaceByPrefix(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')->willReturn([
                ['id' => 'id1', 'url' => 'https://pikachu.com/en'],
            ]);

        $connection
            ->expects($this->once())
            ->method('update')->with(
                'channel_domain',
                ['url' => 'http://localhost/en'],
                ['id' => 'id1']
            );

        $handler = new StagingChannelHandler($connection);

        $domainMapping = [
            ['match' => 'https://pikachu.com', 'type' => 'prefix', 'replace' => 'http://localhost'],
        ];

        $event = new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            $domainMapping
        );

        $handler($event);
    }
}
