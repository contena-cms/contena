<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\SnapshotTesting;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
class ZugferdRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private ChannelContext $channelContext;

    private Context $context;

    private ZugferdRenderer $renderer;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = $this->createShippingMethod();
        $shippingAddressId = Uuid::randomHex();

        $options = [
            'defaultShippingAddressId' => $shippingAddressId,
        ];

        $additionalAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Maximilian',
            'lastName' => 'Musterfrau',
            'street' => 'Ebbinghoff 10a',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
        ];

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser($options, $additionalAddress),
                ChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
        $this->channelContext->setRuleIds([$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdRenderer::class);
        $this->upsertDocumentSellerAddress(InvoiceRenderer::TYPE);
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $config = [
            'vatId' => 'DE123456789',
            'bankBic' => 'DEUTDEDBFRA',
            'bankIban' => 'DE89370400440532013000',
            'bankName' => 'Deutsche Bank',
            'taxNumber' => '123/456/7890',
            'taxOffice' => 'Finanzamt Musterstadt',
            'companyUrl' => 'https://www.example.com',
            'companyName' => 'Example Company',
            'companyEmail' => 'mail@example.com',
            'companyPhone' => '+49 123 4567890',
            'paymentDueDate' => '+30 days',
            'executiveDirector' => 'Max Mustermann',
            'placeOfFulfillment' => 'Musterstadt',
            'placeOfJurisdiction' => 'Musterstadt',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];

        $operation = new DocumentGenerateOperation(
            $orderId,
            ZugferdRenderer::FILE_EXTENSION,
            $config
        );

        $processedTemplate = $this->renderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);

        $this->assertSnapshot('zugferd_invoice_document_default', [
            [
                'type' => self::TYPE_XML,
                'actual' => $content,
            ],
        ]);
    }
}
