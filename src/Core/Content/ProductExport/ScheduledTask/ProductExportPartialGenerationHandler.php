<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\ProductExport\ProductExportCollection;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\ProductExportException;
use Contena\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Contena\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Contena\Core\Content\ProductExport\Service\ProductExportRendererInterface;
use Contena\Core\Content\ProductExport\Struct\ExportBehavior;
use Contena\Core\Content\ProductExport\Struct\ProductExportResult;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[AsMessageHandler]
final readonly class ProductExportPartialGenerationHandler
{
    /**
     * @internal
     *
     * @param EntityRepository<ProductExportCollection> $productExportRepository
     */
    public function __construct(
        private ProductExportGeneratorInterface $productExportGenerator,
        private AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private EntityRepository $productExportRepository,
        private ProductExportFileHandlerInterface $productExportFileHandler,
        private MessageBusInterface $messageBus,
        private ProductExportRendererInterface $productExportRender,
        private AbstractTranslator $translator,
        private SalesChannelContextServiceInterface $salesChannelContextService,
        private SalesChannelContextPersister $contextPersister,
        private Connection $connection,
        private LanguageLocaleCodeProvider $languageLocaleProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ProductExportPartialGeneration $productExportPartialGeneration): void
    {
        $context = $this->getContext($productExportPartialGeneration);
        $productExport = $this->fetchProductExport($productExportPartialGeneration, $context);

        if (!$productExport) {
            return;
        }

        $offset = $productExportPartialGeneration->getOffset();
        $exportResult = $this->runExport($productExport, $offset, $context);

        $filePath = $this->productExportFileHandler->getFilePath($productExport, true);

        if ($exportResult === null) {
            $this->finalizeExport($productExport, $filePath);

            return;
        }

        $this->productExportFileHandler->writeProductExportContent(
            $exportResult->getContent(),
            $filePath,
            $offset > 0
        );

        if ($exportResult->hasNextBatch()) {
            $this->messageBus->dispatch(
                new ProductExportPartialGeneration(
                    productExportId: $productExportPartialGeneration->getProductExportId(),
                    salesChannelId: $productExportPartialGeneration->getSalesChannelId(),
                    offset: $exportResult->getOffset()
                )
            );

            return;
        }

        $this->finalizeExport($productExport, $filePath);
    }

    private function getContext(ProductExportPartialGeneration $productExportPartialGeneration): Context
    {
        $context = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $productExportPartialGeneration->getSalesChannelId()
        );

        if (!\in_array($context->getSalesChannel()->getTypeId(), ProductExportEntity::ALLOWED_SALES_CHANNEL_TYPE_IDS, true)) {
            throw ProductExportException::salesChannelNotFound();
        }

        return $context->getContext();
    }

    private function fetchProductExport(
        ProductExportPartialGeneration $productExportPartialGeneration,
        Context $context
    ): ?ProductExportEntity {
        $criteria = new Criteria([$productExportPartialGeneration->getProductExportId()]);
        $criteria
            ->addAssociation('salesChannel')
            ->addAssociation('salesChannelDomain.salesChannel')
            ->addAssociation('salesChannelDomain.language.locale')
            ->addAssociation('productStream.filters.queries')
            ->setLimit(1);

        return $this->productExportRepository->search($criteria, $context)->getEntities()->first();
    }

    private function runExport(
        ProductExportEntity $productExport,
        int $offset,
        Context $context
    ): ?ProductExportResult {
        // Mark running on every batch: this refreshes product_export.updated_at, which
        // ProductExportGenerateTaskHandler::isStale() relies on as a heartbeat to detect a
        // stuck export. Skipping it for later batches would make long exports look stale and
        // get re-dispatched while still running.
        $update = [
            'id' => $productExport->getId(),
            'isRunning' => true,
        ];

        if ($offset === 0) {
            $update['nextGenerationAt'] = $this->clock->now()->modify(\sprintf('+%d seconds', $productExport->getInterval()));
        }

        $this->productExportRepository->update([$update], $context);

        return $this->productExportGenerator->generate(
            $productExport,
            new ExportBehavior(
                batchMode: true,
                generateHeader: false,
                generateFooter: false,
                offset: $offset
            )
        );
    }

    private function finalizeExport(ProductExportEntity $productExport, string $filePath): void
    {
        $domain = $productExport->getSalesChannelDomain();

        if ($domain === null) {
            throw ProductExportException::salesChannelDomainNotFound($productExport->getId());
        }

        $contextToken = Uuid::randomHex();
        $this->contextPersister->save(
            $contextToken,
            [
                SalesChannelContextService::CURRENCY_ID => $productExport->getCurrencyId(),
            ],
            $productExport->getSalesChannelId()
        );

        $context = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $productExport->getStorefrontSalesChannelId(),
                $contextToken,
                $domain->getLanguageId(),
                $domain->getCurrencyId() ?? $productExport->getCurrencyId()
            )
        );

        $this->translator->injectSettings(
            $productExport->getStorefrontSalesChannelId(),
            $domain->getLanguageId(),
            $this->languageLocaleProvider->getLocaleForLanguageId($domain->getLanguageId()),
            $context->getContext()
        );

        $headerContent = $this->productExportRender->renderHeader($productExport, $context);
        $footerContent = $this->productExportRender->renderFooter($productExport, $context);
        $finalFilePath = $this->productExportFileHandler->getFilePath($productExport);

        $this->translator->resetInjection();

        $writeProductExportSuccessful = $this->productExportFileHandler->finalizePartialProductExport(
            $filePath,
            $finalFilePath,
            $headerContent,
            $footerContent
        );

        $this->connection->delete('sales_channel_api_context', ['token' => $contextToken]);

        if (!$writeProductExportSuccessful) {
            return;
        }

        $this->productExportRepository->update(
            [
                [
                    'id' => $productExport->getId(),
                    'generatedAt' => $this->clock->now(),
                    'isRunning' => false,
                ],
            ],
            $context->getContext()
        );
    }
}
