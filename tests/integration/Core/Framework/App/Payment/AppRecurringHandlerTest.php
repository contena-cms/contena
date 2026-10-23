<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Contena\Core\Framework\App\Payment\Response\PaymentResponse;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
class AppRecurringHandlerTest extends AbstractAppPaymentHandlerTestCase
{
    public function testRecurring(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'status' => OrderTransactionStates::STATE_PAID,
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);
        $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());

        $request = $this->getLastRequest();
        static::assertNotNull($request);
        $body = $request->getBody()->getContents();

        $appSecret = $this->app->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertTrue($request->hasHeader('contena-shop-signature'));
        static::assertSame(hash_hmac('sha256', $body, $appSecret), $request->getHeaderLine('contena-shop-signature'));
        static::assertNotSame('', $request->getHeaderLine('sw-version'));
        static::assertNotSame('', $request->getHeaderLine(AuthMiddleware::CONTENA_USER_LANGUAGE));
        static::assertNotSame('', $request->getHeaderLine(AuthMiddleware::CONTENA_CONTEXT_LANGUAGE));
        static::assertSame('POST', $request->getMethod());
        static::assertJson($body);
        $content = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('source', $content);
        static::assertSame([
            'url' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId()->id,
            'appVersion' => '1.0.0',
            'inAppPurchases' => null,
        ], $content['source']);

        $this->assertOrderTransactionState(OrderTransactionStates::STATE_PAID, $transactionId);
    }

    public function testItFailsOnErrorResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([
            'message' => 'FOO_BAR_ERROR_MESSAGE',
        ]);

        $this->appendNewResponse($this->signResponse($response->jsonSerialize()));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(AppException::class, $e);
            static::assertSame('The app payment process was interrupted due to the following error:
FOO_BAR_ERROR_MESSAGE', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnUnsignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, [], $json));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

    public function testItFailsOnWronglySignedResponse(): void
    {
        $paymentMethodId = $this->getPaymentMethodId('recurring');
        $orderId = $this->createOrder($paymentMethodId);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId);

        $response = PaymentResponse::create([]);
        $json = \json_encode($response, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($json);

        $this->appendNewResponse(new Response(200, ['contena-app-signature' => 'invalid'], $json));

        $paymentHandler = static::getContainer()->get(AppPaymentHandler::class);

        try {
            $paymentHandler->recurring($this->getRecurringStruct(), Context::createDefaultContext());
        } catch (\Throwable $e) {
            static::assertInstanceOf(ServerException::class, $e);
            static::assertSame('Could not verify the authenticity of the response', $e->getMessage());

            $this->assertOrderTransactionState(OrderTransactionStates::STATE_OPEN, $transactionId);

            return;
        }

        static::fail('Should catch a RecurringException');
    }

}
