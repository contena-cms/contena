<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Commands;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Contena\Core\Content\Media\Thumbnail\ThumbnailService;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'media:generate-thumbnails',
    description: 'Generates thumbnails for all media files',
)]
class GenerateThumbnailsCommand extends Command
{
    private ContenaStyle $io;

    private ?int $batchSize = null;

    private bool $isAsync;

    private bool $isStrict;

    private bool $isForce;

    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(
        private readonly ThumbnailService $thumbnailService,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly MessageBusInterface $messageBus,
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
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of entities per iteration', '50')
            ->addOption(
                'folder-name',
                null,
                InputOption::VALUE_REQUIRED,
                'An optional folder name to create thumbnails'
            )
            ->addOption(
                'async',
                'a',
                InputOption::VALUE_NONE,
                'Queue up batch jobs instead of generating thumbnails directly'
            )
            ->addOption(
                'strict',
                's',
                InputOption::VALUE_NONE,
                'Additionally checks that physical files for existing thumbnails are present'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Regenerates thumbnails for all configured sizes even when a thumbnail already exists, e.g. after changing the thumbnail quality'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ContenaStyle($input, $output);

        if ($this->remoteThumbnailsEnable) {
            $this->io->comment('Remote thumbnails are enabled. Skipping thumbnail generation.');

            return self::FAILURE;
        }

        $this->initializeCommand($input);

        if (!$this->isAsync) {
            $this->generateSynchronous($input);
        } else {
            $this->generateAsynchronous($input);
        }

        return self::SUCCESS;
    }

    private function initializeCommand(InputInterface $input): void
    {
        $this->batchSize = $this->getBatchSizeFromInput($input);
        $this->isAsync = $input->getOption('async');
        $this->isStrict = $input->getOption('strict');
        $this->isForce = $input->getOption('force');
    }

    private function getBatchSizeFromInput(InputInterface $input): int
    {
        $rawInput = $input->getOption('batch-size');

        if (!is_numeric($rawInput)) {
            throw MediaException::invalidBatchSize();
        }

        return (int) $rawInput;
    }

    private function getFolderFilter(string $folderName, Context $context): ?EqualsAnyFilter
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('name', $folderName));

        $searchResult = $this->mediaFolderRepository->search($criteria, $context);

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        return new EqualsAnyFilter('mediaFolderId', $searchResult->getEntities()->getIds());
    }

    /**
     * @param RepositoryIterator<MediaCollection> $iterator
     *
     * @return array{generated: int, skipped: int, errored: int, errors: list<list<string>>}
     */
    private function generateThumbnails(RepositoryIterator $iterator, Context $context): array
    {
        $generated = 0;
        $skipped = 0;
        $errored = 0;
        $errors = [];

        while (($result = $iterator->fetch()) !== null) {
            foreach ($result->getEntities() as $media) {
                try {
                    if ($this->thumbnailService->updateThumbnails($media, $context, $this->isStrict, $this->isForce) > 0) {
                        ++$generated;
                    } else {
                        ++$skipped;
                    }
                } catch (\Throwable $e) {
                    ++$errored;
                    $errors[] = [\sprintf('Cannot process file "%s" (id: %s) due error: %s', $media->getFileName() ?? '', $media->getId(), $e->getMessage())];
                }
            }
            $this->io->progressAdvance($result->getEntities()->count());
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errored' => $errored,
            'errors' => $errors,
        ];
    }

    private function createCriteria(?Filter $folderFilter): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit($this->batchSize);
        $criteria->addFilter(new EqualsFilter('media.mediaFolder.configuration.createThumbnails', true));
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');

        if ($folderFilter !== null) {
            $criteria->addFilter($folderFilter);
        }

        return $criteria;
    }

    private function generateSynchronous(InputInterface $input): void
    {
        $totalMediaCount = 0;
        foreach ($this->createContextIterators($input) as $entry) {
            $totalMediaCount += $entry['iterator']->getTotal();
        }
        $this->io->comment(\sprintf('Generating Thumbnails for %d files. This may take some time...', $totalMediaCount));
        $this->io->progressStart($totalMediaCount);

        $result = [
            'generated' => 0,
            'skipped' => 0,
            'errored' => 0,
            'errors' => [],
        ];
        foreach ($this->createContextIterators($input) as $entry) {
            $contextResult = $this->generateThumbnails($entry['iterator'], $entry['context']);
            $result['generated'] += $contextResult['generated'];
            $result['skipped'] += $contextResult['skipped'];
            $result['errored'] += $contextResult['errored'];
            $result['errors'] = [...$result['errors'], ...$contextResult['errors']];
        }

        $this->io->progressFinish();
        $this->io->table(
            ['Action', 'Number of Media Entities'],
            [
                ['Generated', $result['generated']],
                ['Skipped', $result['skipped']],
                ['Errors', $result['errored']],
            ]
        );

        if ($result['errors'] !== []) {
            if ($this->io->isVerbose()) {
                $errors = $result['errors'];
                $this->io->table(
                    ['Error messages'],
                    $errors
                );
            } else {
                $this->io->warning(\sprintf('Thumbnail generation for %d file(s) failed. Use -v to show the files', \count($result['errors'])));
            }
        }
    }

    private function generateAsynchronous(InputInterface $input): void
    {
        $batchCount = 0;
        $this->io->comment('Generating batch jobs...');
        foreach ($this->createContextIterators($input) as $entry) {
            while (($result = $entry['iterator']->fetch()) !== null) {
                $msg = new UpdateThumbnailsMessage();
                $msg->setStrict($this->isStrict);
                $msg->setForce($this->isForce);
                $msg->setMediaIds($result->getEntities()->getIds());
                $msg->setContext($entry['context']);

                $this->messageBus->dispatch($msg);
                ++$batchCount;
            }
        }
        $this->io->success(\sprintf('Generated %d Batch jobs!', $batchCount));
    }

    /**
     * @return \Generator<int, array{iterator: RepositoryIterator<MediaCollection>, context: Context}>
     */
    private function createContextIterators(InputInterface $input): \Generator
    {
        $folderName = $input->getOption('folder-name');
        $folderName = \is_string($folderName) && $folderName !== '' ? $folderName : null;
        $hasMatchingContext = false;

        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $folderFilter = $folderName === null ? null : $this->getFolderFilter($folderName, $context);
            if ($folderName !== null && $folderFilter === null) {
                continue;
            }

            $hasMatchingContext = true;
            yield [
                'iterator' => new RepositoryIterator($this->mediaRepository, $context, $this->createCriteria($folderFilter)),
                'context' => $context,
            ];
        }

        if ($folderName !== null && !$hasMatchingContext) {
            throw MediaException::mediaFolderNameNotFound($folderName);
        }
    }
}
