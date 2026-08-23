<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'media:delete-local-thumbnails',
    description: 'Deletes all media thumbnail records and physical thumbnail files.',
)]
class DeleteThumbnailsCommand extends Command
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaThumbnailCollection> $thumbnailRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $thumbnailRepository,
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
        private readonly bool $remoteThumbnailsEnable = false,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Delete thumbnails even when remote thumbnails are disabled. The frontend will be missing thumbnails until they are regenerated'
        );
        $this->addOption(
            'orphans',
            'o',
            InputOption::VALUE_NONE,
            'Only delete orphaned thumbnail files without a database record. Referenced thumbnails are kept, so this is safe in every setup'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);

        $force = (bool) $input->getOption('force');
        $orphansOnly = (bool) $input->getOption('orphans');

        if ($force && $orphansOnly) {
            $io->error('The options --force and --orphans cannot be combined: --force deletes all thumbnail files including orphaned ones, while --orphans only deletes orphaned files.');

            return self::INVALID;
        }

        if (!$this->remoteThumbnailsEnable && !$force && !$orphansOnly) {
            $io->comment('Deleting thumbnails is only supported when remote thumbnail is enabled. Use the --force option to delete them anyway, --orphans to only delete files without a database record, or "media:generate-thumbnails --force" to regenerate them in place.');

            return self::FAILURE;
        }

        /** @var list<string> $recordPaths */
        $recordPaths = array_values(array_filter($this->connection->fetchFirstColumn('SELECT `path` FROM `media_thumbnail`')));

        if ($orphansOnly) {
            $deletedCount = $this->deleteOrphanedThumbnailFiles($recordPaths);

            $io->table(
                ['Action', 'Number of thumbnail files'],
                [
                    ['Deleted (orphaned)', $deletedCount],
                    ['Kept (referenced)', \count($recordPaths)],
                ]
            );

            $io->success('Successfully deleted all orphaned thumbnail files.');

            return self::SUCCESS;
        }

        $fileCount = $this->countThumbnailFiles();

        $this->deleteThumbnails();
        $this->deleteThumbnailFiles();

        $io->table(
            ['Action', 'Number of thumbnail files'],
            [
                ['Deleted', $fileCount],
            ]
        );

        $io->success('Successfully deleted all thumbnails records and thumbnails files.');

        return self::SUCCESS;
    }

    private function deleteThumbnails(): void
    {
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $thumbnailIds = $this->thumbnailRepository->searchIds(new Criteria(), $context)->getIds();

            if ($thumbnailIds !== []) {
                $this->thumbnailRepository->delete(
                    array_map(static fn (string $id): array => ['id' => $id], $thumbnailIds),
                    $context,
                );
            }

            $this->clearReadOnlyThumbnails($context);
        }
    }

    private function clearReadOnlyThumbnails(Context $context): void
    {
        $tenantId = $context->getTenantId();
        if ($tenantId === null) {
            $this->connection->executeStatement(
                'UPDATE `media` SET `thumbnails_ro` = NULL WHERE `tenant_id` IS NULL',
            );

            return;
        }

        $this->connection->executeStatement(
            'UPDATE `media` SET `thumbnails_ro` = NULL WHERE `tenant_id` = :tenantId',
            ['tenantId' => Uuid::fromHexToBytes($tenantId)],
        );
    }

    /**
     * Orphaned files have no database record anymore, e.g. because they were left behind under an
     * outdated cache buster path after their media was uploaded again. Files are deleted while
     * iterating, so the tree is walked once and never materialized in memory.
     *
     * @param list<string> $recordPaths
     */
    private function deleteOrphanedThumbnailFiles(array $recordPaths): int
    {
        $recordPaths = array_flip($recordPaths);

        $deleted = 0;
        foreach ([$this->filesystemPublic, $this->filesystemPrivate] as $filesystem) {
            foreach ($filesystem->listContents('thumbnail', true) as $item) {
                if ($item->isFile() && !isset($recordPaths[$item->path()])) {
                    $filesystem->delete($item->path());
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    private function countThumbnailFiles(): int
    {
        $count = 0;
        foreach ([$this->filesystemPublic, $this->filesystemPrivate] as $filesystem) {
            foreach ($filesystem->listContents('thumbnail', true) as $item) {
                if ($item->isFile()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Removes the whole physical thumbnail directory to also catch orphaned files.
     */
    private function deleteThumbnailFiles(): void
    {
        $this->filesystemPublic->deleteDirectory('thumbnail');
        $this->filesystemPrivate->deleteDirectory('thumbnail');
    }
}
