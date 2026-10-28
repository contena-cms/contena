<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\EventListener;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\ProductExport\ProductExportCollection;
use Contena\Core\Content\ProductExport\ProductExportDefinition;
use Contena\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductExportEventListener implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductExportCollection> $productExportRepository
     */
    public function __construct(
        private readonly EntityRepository $productExportRepository,
        private readonly ProductExportFileHandlerInterface $productExportFileHandler,
        private readonly FilesystemOperator $fileSystem
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_export.written' => 'afterWrite',
        ];
    }

    public function afterWrite(EntityWrittenEvent $event): void
    {
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            if (!$this->productExportWritten($writeResult)) {
                continue;
            }

            $primaryKey = $writeResult->getPrimaryKey();
            $primaryKey = \is_array($primaryKey) ? $primaryKey['id'] : $primaryKey;

            $this->productExportRepository->update(
                [
                    [
                        'id' => $primaryKey,
                        'generatedAt' => null,
                        'nextGenerationAt' => null,
                        // Reset stuck runs when a user/admin edits the export
                        'isRunning' => false,
                    ],
                ],
                $event->getContext()
            );

            $productExport = $this->productExportRepository->search(new Criteria([$primaryKey]), $event->getContext())->getEntities()->first();
            if (!$productExport) {
                continue;
            }

            $filePath = $this->productExportFileHandler->getFilePath($productExport);
            if ($this->fileSystem->fileExists($filePath)) {
                $this->fileSystem->delete($filePath);
            }
        }
    }

    private function productExportWritten(EntityWriteResult $writeResult): bool
    {
        return $writeResult->getEntityName() === ProductExportDefinition::ENTITY_NAME
            && !\array_key_exists('generatedAt', $writeResult->getPayload())
            && !\array_key_exists('nextGenerationAt', $writeResult->getPayload())
            && !\array_key_exists('isRunning', $writeResult->getPayload());
    }
}
