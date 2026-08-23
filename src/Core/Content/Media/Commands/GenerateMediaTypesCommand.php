<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Commands;

use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\TypeDetector\TypeDetector;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'media:generate-media-types',
    description: 'Generates media types for all media files',
)]
class GenerateMediaTypesCommand extends Command
{
    private ContenaStyle $io;

    private ?int $batchSize = null;

    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly TypeDetector $typeDetector,
        private readonly EntityRepository $mediaRepository,
        private readonly TenantScopeContextProvider $tenantScopeContextProvider,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch Size')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new ContenaStyle($input, $output);

        $this->batchSize = $this->validateBatchSize($input);

        $this->io->comment('Starting to generate MediaTypes. This may take some time...');
        $mediaCount = 0;
        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $mediaCount += $this->getMediaCount($context);
        }
        $this->io->progressStart($mediaCount);

        foreach ($this->tenantScopeContextProvider->getContexts() as $context) {
            $this->detectMediaTypes($context);
        }

        $this->io->progressFinish();

        return self::SUCCESS;
    }

    private function validateBatchSize(InputInterface $input): int
    {
        $batchSize = $input->getOption('batch-size');
        if ($batchSize === null) {
            return 100;
        }

        if (!is_numeric($batchSize)) {
            throw MediaException::invalidBatchSize();
        }

        return (int) $batchSize;
    }

    private function getMediaCount(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->setLimit(1);
        $result = $this->mediaRepository->search($criteria, $context);

        return $result->getTotal();
    }

    private function detectMediaTypes(Context $context): void
    {
        $criteria = $this->createCriteria();

        do {
            $result = $this->mediaRepository->search($criteria, $context);

            $medias = $result->getEntities();
            foreach ($medias as $media) {
                $this->detectMediaType($context, $media);
            }
            $this->io->progressAdvance($result->getEntities()->count());
            $criteria->setOffset((int) $criteria->getOffset() + (int) $this->batchSize);
        } while ($result->getTotal() > $this->batchSize);
    }

    private function detectMediaType(Context $context, MediaEntity $media): void
    {
        if (!$media->hasFile()) {
            return;
        }

        $file = new MediaFile(
            $media->getUrl(),
            $media->getMimeType() ?? '',
            $media->getFileExtension() ?? '',
            $media->getFileSize() ?? 0
        );

        $type = $this->typeDetector->detect($file);
        $changeSet = ['id' => $media->getId(), 'mediaTypeRaw' => serialize($type)];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($changeSet): void {
            $this->mediaRepository->upsert([$changeSet], $context);
        });
    }

    private function createCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
        $criteria->setLimit($this->batchSize);

        return $criteria;
    }
}
