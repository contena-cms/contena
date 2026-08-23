<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Commands\DeleteThumbnailsCommand;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DeleteThumbnailsCommand::class)]
class DeleteThumbnailsCommandTest extends TestCase
{
    public function testExecuteWithRemoteThumbnailsDisabled(): void
    {
        $command = new DeleteThumbnailsCommand(
            static::createStub(Connection::class),
            StaticEntityRepository::of(MediaThumbnailCollection::class),
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            $this->createContextProvider([Context::createDefaultContext()]),
            false,
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        static::assertStringContainsStringIgnoringLineEndings('// Deleting thumbnails is only supported when remote thumbnail is enabled.', trim($commandTester->getDisplay()));
    }

    public function testExecuteWithRemoteThumbnailsEnabled(): void
    {
        $platformThumbnailId = Uuid::randomHex();
        $tenantThumbnailId = Uuid::randomHex();
        $tenantContext = Context::createTenantContext(Uuid::randomHex());
        $thumbnailRepository = StaticEntityRepository::of(MediaThumbnailCollection::class, [
            function (Criteria $criteria, Context $context) use ($platformThumbnailId): array {
                static::assertNull($context->getTenantId());

                return [$platformThumbnailId];
            },
            function (Criteria $criteria, Context $context) use ($tenantContext, $tenantThumbnailId): array {
                static::assertSame($tenantContext->getTenantId(), $context->getTenantId());

                return [$tenantThumbnailId];
            },
        ]);
        $command = new DeleteThumbnailsCommand(
            static::createStub(Connection::class),
            $thumbnailRepository,
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            $this->createContextProvider([Context::createDefaultContext(), $tenantContext]),
            true,
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        static::assertStringContainsString('Successfully deleted all thumbnails records and thumbnails files.', $commandTester->getDisplay());
        static::assertSame(
            [[['id' => $platformThumbnailId]], [['id' => $tenantThumbnailId]]],
            $thumbnailRepository->deletes,
        );
    }

    public function testExecuteWithRemoteThumbnailsDisabledAndForce(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $command = new DeleteThumbnailsCommand(
            $connection,
            $thumbnailRepository,
            $filesystemPublic,
            $filesystemPrivate,
            $this->createContextProvider([Context::createDefaultContext()]),
            false,
        );

        $thumbnailId = Uuid::randomHex();
        $thumbnailPath = 'thumbnail/aa/bb/cc/test_100x100.png';
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT `path` FROM `media_thumbnail`')
            ->willReturn([$thumbnailPath]);
        $filesystemPublic->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([
                new FileAttributes($thumbnailPath),
                new FileAttributes('thumbnail/dd/ee/ff/orphan_100x100.png'),
            ]));
        $filesystemPrivate->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([]));
        $thumbnailRepository->expects($this->once())->method('delete')->with(
            [['id' => $thumbnailId]],
            static::isInstanceOf(Context::class),
        );

        $connection->expects($this->once())->method('executeStatement')->with(
            'UPDATE `media` SET `thumbnails_ro` = NULL WHERE `tenant_id` IS NULL',
        );
        $filesystemPublic->expects($this->once())->method('deleteDirectory')->with('thumbnail');
        $filesystemPrivate->expects($this->once())->method('deleteDirectory')->with('thumbnail');

        $thumbnailRepository->method('searchIds')->willReturnCallback(
            static fn (Criteria $criteria, Context $context): IdSearchResult => IdSearchResult::fromIds([$thumbnailId], $criteria, $context),
        );

        $commandTester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $commandTester->execute(['--force' => true]));
        static::assertStringContainsString('Successfully deleted all thumbnails records and thumbnails files.', $commandTester->getDisplay());
    }

    public function testExecuteFailsWhenForceAndOrphansAreCombined(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $command = new DeleteThumbnailsCommand(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(FilesystemOperator::class),
            $this->createContextProvider([Context::createDefaultContext()]),
            false,
        );

        $commandTester = new CommandTester($command);
        static::assertSame(Command::INVALID, $commandTester->execute(['--force' => true, '--orphans' => true]));
        static::assertStringContainsString('The options --force and --orphans cannot be combined', $commandTester->getDisplay());
    }

    public function testExecuteWithOrphansOnlyDeletesUnreferencedFiles(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $command = new DeleteThumbnailsCommand(
            $connection,
            $thumbnailRepository,
            $filesystemPublic,
            $filesystemPrivate,
            $this->createContextProvider([Context::createDefaultContext()]),
            false,
        );

        $referenced = 'thumbnail/aa/bb/cc/test_100x100.png';
        $orphan = 'thumbnail/dd/ee/ff/orphan_100x100.png';
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with('SELECT `path` FROM `media_thumbnail`')
            ->willReturn([$referenced]);
        $filesystemPublic->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([new FileAttributes($referenced), new FileAttributes($orphan)]));
        $filesystemPrivate->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([]));
        $filesystemPublic->expects($this->once())->method('delete')->with($orphan);
        $filesystemPublic->expects($this->never())->method('deleteDirectory');
        $filesystemPrivate->expects($this->never())->method('deleteDirectory');
        $thumbnailRepository->expects($this->never())->method('delete');
        $connection->expects($this->never())->method('executeStatement');

        $commandTester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $commandTester->execute(['--orphans' => true]));
        static::assertStringContainsString('Successfully deleted all orphaned thumbnail files.', $commandTester->getDisplay());
    }

    /**
     * @param list<Context> $contexts
     */
    private function createContextProvider(array $contexts): TenantScopeContextProvider
    {
        $contextProvider = static::createStub(TenantScopeContextProvider::class);
        $contextProvider->method('getContexts')->willReturn((static function () use ($contexts): \Generator {
            yield from $contexts;
        })());

        return $contextProvider;
    }
}
