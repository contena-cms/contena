<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class PaymentHandlerRegistryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private PaymentHandlerRegistry $paymentHandlerRegistry;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    /**
     * @var EntityRepository<AppPaymentMethodCollection>
     */
    private EntityRepository $appPaymentMethodRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->appPaymentMethodRepository = static::getContainer()->get('app_payment_method.repository');
        $this->paymentHandlerRegistry = static::getContainer()->get(PaymentHandlerRegistry::class);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');
        $appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testGetHandler(): void
    {
        $paymentMethod = $this->getPaymentMethod(InvoicePayment::class);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf(InvoicePayment::class, $handler);
    }

    public function testAppResolve(): void
    {
        $appPaymentData = [
            'id' => Uuid::randomHex(),
            'identifier' => 'apptest',
            'appName' => 'apptest',
            'payUrl' => null,
            'finalizeUrl' => null,
            'validateUrl' => null,
            'captureUrl' => null,
            'refundUrl' => null,
        ];

        $paymentMethod = $this->getPaymentMethod('refundable');
        $appPaymentData['paymentMethodId'] = $paymentMethod->getId();

        $this->appPaymentMethodRepository->upsert([$appPaymentData], Context::createDefaultContext());

        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());

        static::assertInstanceOf(AppPaymentHandler::class, $handler);
    }

    private function getPaymentMethod(string $handler): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $criteria->addAssociation('app');

        /** @var PaymentMethodEntity|null $method */
        $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$method) {
            $method = [
                'id' => Uuid::randomHex(),
                'technicalName' => 'payment_test',
                'handlerIdentifier' => $handler,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $handler,
                    ],
                ],
            ];

            $this->paymentMethodRepository->upsert([$method], Context::createDefaultContext());

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
            $criteria->addAssociation('app');

            /** @var PaymentMethodEntity|null $method */
            $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        }

        static::assertNotNull($method);

        return $method;
    }
}
