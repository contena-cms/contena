<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Context\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Contena\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\Payload\AppPayloadStruct;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppContextGatewayPayloadService::class)]
class AppContextGatewayPayloadServiceTest extends TestCase
{
    public function testRequest(): void
    {
        $context = Generator::generateChannelContext();
        $cart = Generator::createCart();
        $customData = new RequestDataBag(['foo' => 'bar']);

        $app = new AppEntity();
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $payload = new ContextGatewayPayloadStruct($cart, $context, $customData);
        $encodedPayload = \json_encode($this->encodePayload($payload), \JSON_THROW_ON_ERROR);

        $helper = $this->createMock(AppPayloadServiceHelper::class);
        $helper
            ->expects($this->once())
            ->method('createRequestOptions')
            ->with($payload, $app, $context->getContext())
            ->willReturn($this->buildTestPayload($context->getContext(), $encodedPayload));

        $response = [
            [
                'command' => 'context_test-command',
                'payload' => ['foo' => 'bar'],
            ],
        ];

        $handler = new MockHandler();
        $handler->append($this->buildResponse($response));

        $client = new Client(['handler' => $handler]);

        $service = new AppContextGatewayPayloadService($helper, $client);

        $gatewayResponse = $service->request('https://example.com', $payload, $app);

        static::assertInstanceOf(AppContextGatewayResponse::class, $gatewayResponse);
        static::assertSame($response, $gatewayResponse->getCommands());
    }

    public function testRequestAppThrowsException(): void
    {
        $context = Generator::generateChannelContext();
        $cart = Generator::createCart();
        $customData = new RequestDataBag(['foo' => 'bar']);

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $payload = new ContextGatewayPayloadStruct($cart, $context, $customData);

        $e = new BadResponseException('Bad', new Request('POST', 'https://example.com'), new Response());

        $handler = new MockHandler();
        $handler->append($e);

        $client = new Client(['handler' => $handler]);

        $service = new AppContextGatewayPayloadService(
            static::createStub(AppPayloadServiceHelper::class),
            $client,
        );

        $this->expectExceptionObject(AppException::gatewayRequestFailed('TestApp', 'context', $e));

        $service->request('https://example.com', $payload, $app);
    }

    public function testRequestWithMalformedJsonThrows(): void
    {
        $context = Generator::generateChannelContext();
        $cart = Generator::createCart();
        $customData = new RequestDataBag(['foo' => 'bar']);

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $payload = new ContextGatewayPayloadStruct($cart, $context, $customData);

        $helper = $this->createMock(AppPayloadServiceHelper::class);
        $helper
            ->expects($this->once())
            ->method('createRequestOptions')
            ->willReturn($this->buildTestPayload($context->getContext(), '[]'));

        $handler = new MockHandler();
        $handler->append(new Response(200, [], '{'));

        $service = new AppContextGatewayPayloadService(
            $helper,
            new Client(['handler' => $handler]),
        );

        try {
            $service->request('https://example.com', $payload, $app);
            static::fail('Expected malformed context gateway JSON to be wrapped.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_GATEWAY_REQUEST_FAILED, $e->getErrorCode());
            static::assertNull($e->getPrevious());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function encodePayload(ContextGatewayPayloadStruct $payload): array
    {
        return [
            'channelContext' => $payload->getChannelContext()->jsonSerialize(),
            'cart' => $payload->getCart()->jsonSerialize(),
            'data' => $payload->getData(),
        ];
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function buildResponse(array $body): ResponseInterface
    {
        return new Response(200, [], \json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function buildTestPayload(Context $context, string $payload): AppPayloadStruct
    {
        return new AppPayloadStruct([
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'some-secret',
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $payload,
        ]);
    }
}
