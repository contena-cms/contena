<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Contena\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
class ReferenceInvoiceLoaderTest extends TestCase
{
    use DocumentTrait;

    private ReferenceInvoiceLoader $referenceInvoiceLoader;

    private Context $context;

    private ChannelContext $channelContext;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referenceInvoiceLoader = static::getContainer()->get(ReferenceInvoiceLoader::class);
        $this->context = Context::createDefaultContext();
        $userId = $this->createUser();

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $userId,
            ]
        );

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testLoadWithoutDocument(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `document`');

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $invoice = $this->referenceInvoiceLoader->load($orderId);

        static::assertCount(0, $invoice);
    }

    public function testLoadWithoutReferenceDocumentIdWithUnsentDocuments(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        // Create two documents, the latest unsent invoice will be returned
        $invoiceStruct = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        $invoiceStructLatest = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        static::assertNotNull($invoiceStruct);
        static::assertNotNull($invoiceStructLatest);
        $invoice = $this->referenceInvoiceLoader->load($orderId);

        static::assertNotSame('', $invoice['id']);
        static::assertSame($invoice['id'], $invoiceStructLatest->getId());
    }

    public function testLoadWithoutReferenceDocumentIdWithOneSentDocument(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        // Create two documents, the latest sent invoice will be returned
        $invoiceStruct = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        $invoiceStructLatest = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        static::assertNotNull($invoiceStruct);
        static::assertNotNull($invoiceStructLatest);

        $this->connection->executeStatement(<<<'SQL'
            UPDATE document SET sent = 1 WHERE id = :id;
        SQL, ['id' => Uuid::fromHexToBytes($invoiceStruct->getId())]);

        $invoice = $this->referenceInvoiceLoader->load($orderId);

        static::assertNotSame('', $invoice['id']);
        static::assertSame($invoice['id'], $invoiceStruct->getId());
    }

    public function testLoadWithReferenceDocumentId(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        // Create two documents, the one with passed referenceInvoiceId will be returned
        $invoiceStruct = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        static::assertNotNull($invoiceStruct);
        $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();

        $invoice = $this->referenceInvoiceLoader->load($orderId, $invoiceStruct->getId(), $invoiceStruct->getDeepLinkCode());

        static::assertSame($invoiceStruct->getId(), $invoice['id']);
        static::assertSame($orderId, $invoice['orderId']);
        static::assertSame(Defaults::LIVE_VERSION, $invoice['orderVersionId']);
        static::assertNotSame('', $invoice['documentNumber']);
    }

    public function testLoadWithEmbeddedZugferdReferenceDocumentId(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $invoiceStruct = $this->createDocument(
            ZugferdEmbeddedRenderer::TYPE,
            $orderId,
            ['companyName' => 'Test Company'],
            $this->context
        )->first();

        static::assertNotNull($invoiceStruct);

        $invoice = $this->referenceInvoiceLoader->load($orderId, $invoiceStruct->getId(), $invoiceStruct->getDeepLinkCode());

        static::assertSame($invoiceStruct->getId(), $invoice['id']);
        static::assertSame($orderId, $invoice['orderId']);
        static::assertSame(Defaults::LIVE_VERSION, $invoice['orderVersionId']);
        static::assertNotSame('', $invoice['documentNumber']);
    }

    public function testLoadWithZugferdReferenceDocumentId(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $invoiceStruct = $this->createDocument(
            ZugferdRenderer::TYPE,
            $orderId,
            ['companyName' => 'Test Company'],
            $this->context
        )->first();

        static::assertNotNull($invoiceStruct);

        $invoice = $this->referenceInvoiceLoader->load($orderId, $invoiceStruct->getId(), $invoiceStruct->getDeepLinkCode());

        static::assertSame($invoiceStruct->getId(), $invoice['id']);
        static::assertSame($orderId, $invoice['orderId']);
        static::assertSame(Defaults::LIVE_VERSION, $invoice['orderVersionId']);
        static::assertNotSame('', $invoice['documentNumber']);
    }
}
