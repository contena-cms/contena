<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Document\DocumentException;
use Contena\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\DocumentV2\DocumentCollection;
use Contena\Core\Checkout\DocumentV2\DocumentDefinition;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class DocumentDeleteSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use DocumentTrait;
    use KernelTestBehaviour;

    private Context $context;

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    private Connection $connection;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser(),
            ]
        );

        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testDeleteDocumentShouldDeleteDependingMediaEntities(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $documentGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($documentGenerationResult);

        $documentId = $documentGenerationResult->getId();
        $mediaId = $documentGenerationResult->getMediaId();
        static::assertNotNull($mediaId);
        $a11yMediaId = $documentGenerationResult->getA11yMediaId();
        static::assertNotNull($a11yMediaId);

        static::assertTrue($this->hasMediaEntity($mediaId));
        static::assertTrue($this->hasMediaEntity($a11yMediaId));

        $deletedDocumentIds = [];
        $deletedMediaIds = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            EntityDeleteEvent::class,
            function (EntityDeleteEvent $event) use (&$deletedDocumentIds, &$deletedMediaIds): void {
                $deletedDocumentIds = [...$deletedDocumentIds, ...$event->getIds(DocumentDefinition::ENTITY_NAME)];
                $deletedMediaIds = [...$deletedMediaIds, ...$event->getIds(MediaDefinition::ENTITY_NAME)];
            }
        );

        $this->documentRepository->delete([['id' => $documentGenerationResult->getId()]], $this->context);

        static::assertContains(
            $documentId,
            $deletedDocumentIds,
            'DocumentDeleteSubscriber should be triggered to delete media entities.'
        );

        static::assertContains($mediaId, $deletedMediaIds, 'MediaDeletionSubscriber should be triggered and delete media files.');
        static::assertContains($a11yMediaId, $deletedMediaIds, 'MediaDeletionSubscriber should be triggered and delete media files.');

        static::assertFalse($this->hasMediaEntity($mediaId), 'Media entity should be deleted when document is deleted.');
        static::assertFalse($this->hasMediaEntity($a11yMediaId), 'Media entity should be deleted when document is deleted.');
    }

    public function testDeleteDocumentWhichDependsOnOtherDocumentShouldThrowException(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentNumber = $this->connection->fetchOne(
            'SELECT document_number FROM document WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($creditNoteDocumentGenerationResult->getId())],
        );

        static::expectExceptionObject(DocumentException::documentHasDependentDocuments(
            [
                \sprintf(
                    '%s %s (%s)',
                    CreditNoteRenderer::TYPE,
                    $creditNoteDocumentNumber ?? 'unknown',
                    $creditNoteDocumentGenerationResult->getId()
                ),
            ]
        ));

        $this->documentRepository->delete([['id' => $invoiceDocumentId]], $this->context);
    }

    public function testDeleteDocumentsInBulkShouldThrowExceptionAndReturnJustDependedDocumentsWhichAreNotRequestedForDeletion(): void
    {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentId = $creditNoteDocumentGenerationResult->getId();

        /*
         * add second credit item and credit note to have more than one dependent document
         */
        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult2 = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult2);

        $creditNoteDocumentId2 = $creditNoteDocumentGenerationResult2->getId();

        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_DELETE,
                'entity' => static::getContainer()->get(DocumentDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $creditNoteDocumentId,
                    ],
                    [
                        'id' => $invoiceDocumentId,
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            Request::METHOD_POST,
            '/api/_action/sync',
            [],
            [],
            [],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $content);
        static::assertCount(1, $content['errors']);
        $errorDetail = $content['errors'][0]['detail'];
        static::assertStringContainsString(': credit_note', $errorDetail);
        static::assertStringContainsString(\sprintf(' (%s).', $creditNoteDocumentId2), $errorDetail);
        static::assertStringNotContainsString(\sprintf(' (%s).', $creditNoteDocumentId), $errorDetail);
    }

    #[TestWith(['dependingDocumentFirst' => true], 'first depending document (credit note), second parent document (invoice)')]
    #[TestWith(['dependingDocumentFirst' => false], 'first parent document (invoice), second depending document (credit note)')]
    public function testDeleteDocumentsInBulkShouldNotThrowExceptionWhenDependentDocumentIsInRequestList(
        bool $dependingDocumentFirst
    ): void {
        $orderId = $this->persistCart($this->generateDemoCart(1));
        $invoiceGenerationResult = $this->createDocument(
            InvoiceRenderer::TYPE,
            $orderId,
            [],
            $this->context,
        )->first();
        static::assertNotNull($invoiceGenerationResult);

        $invoiceDocumentId = $invoiceGenerationResult->getId();
        $invoiceMediaId = $invoiceGenerationResult->getMediaId();
        static::assertNotNull($invoiceMediaId);
        $invoiceA11yMediaId = $invoiceGenerationResult->getA11yMediaId();
        static::assertNotNull($invoiceA11yMediaId);

        static::assertTrue($this->hasMediaEntity($invoiceMediaId));
        static::assertTrue($this->hasMediaEntity($invoiceA11yMediaId));

        $this->addCreditItemToOrder($orderId);

        $creditNoteDocumentGenerationResult = $this->createDocument(
            CreditNoteRenderer::TYPE,
            $orderId,
            ['referencedDocumentId' => $invoiceDocumentId],
            $this->context,
        )->first();
        static::assertNotNull($creditNoteDocumentGenerationResult);

        $creditNoteDocumentId = $creditNoteDocumentGenerationResult->getId();
        $creditNoteMediaId = $creditNoteDocumentGenerationResult->getMediaId();
        static::assertNotNull($creditNoteMediaId);
        $creditNoteA11yMediaId = $creditNoteDocumentGenerationResult->getA11yMediaId();
        static::assertNotNull($creditNoteA11yMediaId);

        static::assertTrue($this->hasMediaEntity($creditNoteMediaId));
        static::assertTrue($this->hasMediaEntity($creditNoteA11yMediaId));

        $payload = $dependingDocumentFirst
            ? [['id' => $creditNoteDocumentId], ['id' => $invoiceDocumentId]]
            : [['id' => $invoiceDocumentId], ['id' => $creditNoteDocumentId]];

        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_DELETE,
                'entity' => static::getContainer()->get(DocumentDefinition::class)->getEntityName(),
                'payload' => $payload,
            ],
        ];

        $this->getBrowser()->request(
            Request::METHOD_POST,
            '/api/_action/sync',
            [],
            [],
            [],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $documents = $content['deleted']['document'];
        static::assertCount(2, $documents);
        foreach ($documents as $document) {
            static::assertContains($document, [$invoiceDocumentId, $creditNoteDocumentId]);
        }
    }

    private function addCreditItemToOrder(string $orderId): void
    {
        $this->orderRepository->upsert(
            [[
                'id' => $orderId,
                'lineItems' => [
                    [
                        'id' => Uuid::randomHex(),
                        'identifier' => Uuid::randomHex(),
                        'quantity' => 1,
                        'label' => 'label',
                        'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
            ]],
            Context::createDefaultContext()
        );
    }

    private function hasMediaEntity(string $mediaId): bool
    {
        return (bool) $this->connection->fetchAssociative(
            'SELECT * FROM media WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($mediaId),
            ],
        );
    }
}
