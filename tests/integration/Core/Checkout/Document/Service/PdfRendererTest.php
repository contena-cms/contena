<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Document\DocumentConfiguration;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Contena\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Service\PdfRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
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
class PdfRendererTest extends TestCase
{
    use DocumentTrait;

    private ChannelContext $channelContext;

    private Context $context;

    private DeliveryNoteRenderer $deliveryNoteRenderer;

    private DocumentGenerator $documentGenerator;

    private PdfRenderer $pdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $priceRuleId = Uuid::randomHex();

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser(),
            ]
        );

        $this->channelContext->setRuleIds([$priceRuleId]);
        $this->deliveryNoteRenderer = static::getContainer()->get(DeliveryNoteRenderer::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->pdfRenderer = static::getContainer()->get(PdfRenderer::class);
    }

    public function testRender(): void
    {
        // generates one line item for each tax
        $cart = $this->generateDemoCart(3);

        // generates credit items for each price
        $orderId = $this->persistCart($cart);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());

        $invoice = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($invoice);
        $invoiceId = $invoice->getId();

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            [
                'displayLineItems' => true,
                'itemsPerPage' => 10,
                'displayFooter' => true,
                'displayHeader' => true,
            ],
            $invoiceId
        );

        $processedTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

        $rendered = $processedTemplate->getSuccess()[$orderId];

        $generatorOutput = $this->pdfRenderer->render($rendered);
        static::assertNotSame('', $generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertSame('application/pdf', $finfo->buffer($generatorOutput));
    }
}
