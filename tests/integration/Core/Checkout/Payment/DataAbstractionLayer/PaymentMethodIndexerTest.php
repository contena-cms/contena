<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer;
use Contena\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexingMessage;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class PaymentMethodIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentMethodIndexer $indexer;

    private Context $context;

    protected function setUp(): void
    {
        $this->indexer = static::getContainer()->get(PaymentMethodIndexer::class);
        $this->context = Context::createDefaultContext();
    }

    public function testIndexerName(): void
    {
        static::assertSame(
            'payment_method.indexer',
            $this->indexer->getName()
        );
    }

    public function testGeneratesDistinguishablePaymentNameIfPaymentIsProvidedByExtension(): void
    {
        $paymentRepository = static::getContainer()->get('payment_method.repository');

        $paymentRepository->create(
            [
                [
                    'id' => $creditCardPaymentId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Credit card',
                        'de-DE' => 'Kreditkarte',
                    ],
                    'technicalName' => 'payment_creaditcard',
                    'active' => true,
                ],
                [
                    'id' => $invoicePaymentByContenaPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnungskauf',
                    ],
                    'technicalName' => 'payment_invoice',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Contena',
                        'baseClass' => 'Swag\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Contena (English)',
                            'de-DE' => 'Contena (Deutsch)',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnung',
                    ],
                    'technicalName' => 'payment_invoiceplugin',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Plugin',
                        'baseClass' => 'Plugin\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Plugin (English)',
                            'de-DE' => 'Plugin (Deutsch)',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByAppId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnung',
                    ],
                    'technicalName' => 'payment_App_identifier',
                    'active' => true,
                    'appPaymentMethod' => [
                        'identifier' => 'identifier',
                        'appName' => 'appName',
                        'app' => [
                            'name' => 'App',
                            'path' => 'path',
                            'version' => '1.0.0',
                            'label' => 'App',
                            'integration' => [
                                'accessKey' => 'accessKey',
                                'secretAccessKey' => 'secretAccessKey',
                                'label' => 'Integration',
                            ],
                            'aclRole' => [
                                'name' => 'aclRole',
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );

        /** @var PaymentMethodCollection $payments */
        $payments = $paymentRepository
            ->search(new Criteria(), $this->context)
            ->getEntities();

        $creditCardPayment = $payments->get($creditCardPaymentId);
        static::assertNotNull($creditCardPayment);
        static::assertSame('Credit card', $creditCardPayment->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByContenaPlugin */
        $invoicePaymentByContenaPlugin = $payments->get($invoicePaymentByContenaPluginId);
        static::assertSame('Invoice | Contena (English)', $invoicePaymentByContenaPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertSame('Invoice | Plugin (English)', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertSame('Invoice | App', $invoicePaymentByApp->getDistinguishableName());

        /** @var PaymentMethodEntity $paidInAdvance */
        $paidInAdvance = $payments
            ->filterByProperty('name', 'Paid in advance')
            ->first();

        static::assertSame($paidInAdvance->getTranslation('name'), $paidInAdvance->getTranslation('distinguishableName'));

        $germanContext = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]
        );

        /** @var PaymentMethodCollection $payments */
        $payments = $paymentRepository
            ->search(new Criteria(), $germanContext)
            ->getEntities();

        $creditCardPayment = $payments->get($creditCardPaymentId);
        static::assertNotNull($creditCardPayment);
        static::assertSame('Kreditkarte', $creditCardPayment->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByContenaPlugin */
        $invoicePaymentByContenaPlugin = $payments->get($invoicePaymentByContenaPluginId);
        static::assertSame('Rechnungskauf | Contena (Deutsch)', $invoicePaymentByContenaPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertSame('Rechnung | Plugin (Deutsch)', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertSame('Rechnung | App', $invoicePaymentByApp->getDistinguishableName());
    }

    public function testPaymentMethodIndexerNotLooping(): void
    {
        // Setup payment method(s)
        /** @var EntityRepository<PaymentMethodCollection> $paymentRepository */
        $paymentRepository = static::getContainer()->get('payment_method.repository');

        $paymentMethodId = Uuid::randomHex();

        $this->context->state(static function (Context $context) use ($paymentRepository, $paymentMethodId): void {
            $paymentRepository->create(
                [
                    [
                        'id' => $paymentMethodId,
                        'name' => [
                            'en-GB' => 'Credit card',
                            'de-DE' => 'Kreditkarte',
                        ],
                        'technicalName' => 'payment_creditcard_test',
                        'active' => true,
                        'plugin' => [
                            'name' => 'Plugin',
                            'baseClass' => 'Plugin\MyPlugin',
                            'autoload' => [],
                            'version' => '1.0.0',
                            'label' => [
                                'en-GB' => 'Plugin (English)',
                                'de-DE' => 'Plugin (Deutsch)',
                            ],
                        ],
                    ],
                ],
                $context
            );
        }, EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE);

        // Run indexer
        $messageBus = static::getContainer()->get('messenger.default_bus');
        static::assertInstanceOf(TraceableMessageBus::class, $messageBus);
        $messageBus->reset();
        $ids = [$paymentMethodId];

        $this->context->state(function (Context $context) use ($ids): void {
            $message = new PaymentMethodIndexingMessage($ids, null, $context);
            $this->indexer->handle($message);
        }, EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE);

        // Check messenger if there is another new PaymentMethodIndexingMessage (it shouldn't)
        /** @var TraceableMessageBus $messageBus */
        $messages = $messageBus->getDispatchedMessages();
        static::assertCount(0, $messages);
    }
}
