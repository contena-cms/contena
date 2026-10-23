<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Hmac\Guzzle;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Hmac\RequestSigner;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\App\GuzzleHistoryCollector;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class AuthMiddlewareTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->resetHistory();
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testSetDefaultHeaderWithAdminApiSource(): void
    {
        $middleware = new AuthMiddleware('6.4', static::getContainer()->get(AppLocaleProvider::class));
        $request = new Request('POST', 'https://example.local');

        $request = $middleware->getDefaultHeaderRequest($request, [AuthMiddleware::APP_REQUEST_CONTEXT => Context::createDefaultContext()]);

        static::assertArrayHasKey('sw-version', $request->getHeaders());
        static::assertSame('6.4', $request->getHeader('sw-version')[0]);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $request->getHeader(AuthMiddleware::CONTENA_CONTEXT_LANGUAGE)[0]);
        static::assertSame('en-GB', $request->getHeader(AuthMiddleware::CONTENA_USER_LANGUAGE)[0]);
    }

    public function testSetDefaultHeaderWithSaleChannelApiSource(): void
    {
        $middleware = new AuthMiddleware('6.4', static::getContainer()->get(AppLocaleProvider::class));
        $request = new Request('POST', 'https://example.local');

        $request = $middleware->getDefaultHeaderRequest($request, [AuthMiddleware::APP_REQUEST_CONTEXT => $this->channelContext->getContext()]);

        static::assertArrayHasKey('sw-version', $request->getHeaders());
        static::assertSame('6.4', $request->getHeader('sw-version')[0]);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $request->getHeader(AuthMiddleware::CONTENA_CONTEXT_LANGUAGE)[0]);
        static::assertSame('en-GB', $request->getHeader(AuthMiddleware::CONTENA_USER_LANGUAGE)[0]);
    }

    public function testSetDefaultHeaderExist(): void
    {
        $middleware = new AuthMiddleware('6.4', static::getContainer()->get(AppLocaleProvider::class));
        $request = new Request('POST', 'https://example.local', ['sw-version' => '6.5']);

        $request = $middleware->getDefaultHeaderRequest($request, []);

        static::assertArrayHasKey('sw-version', $request->getHeaders());
        static::assertSame('6.5', $request->getHeader('sw-version')[0]);
    }

    public function testCorrectSignRequest(): void
    {
        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'secret',
            ],
                'body' => 'test', ];

        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');
        $client->post(new Uri('https://example.local'), $optionsRequest);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertArrayHasKey(RequestSigner::CONTENA_SHOP_SIGNATURE, $request->getHeaders());

        $historyCollector = static::getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);
        static::assertSame(['example.local:443:93.184.216.34'], $historyCollector->getHistory()[0]['options']['curl'][\CURLOPT_RESOLVE] ?? null);
    }

    public function testSignsForwardedRequestWhenFollowingRedirect(): void
    {
        $this->appendNewResponse(new Response(301, ['Location' => 'https://example.local/moved']));
        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');
        $client->post(new Uri('https://example.local'), [
            AuthMiddleware::APP_REQUEST_TYPE => [AuthMiddleware::APP_SECRET => 'secret'],
            'body' => 'test',
        ]);

        static::assertSame(2, $this->getRequestCount());

        // Without the strict redirect policy Guzzle would downgrade this to a bodyless GET,
        // which makes the signer skip the signature and the app receive an unsigned request.
        $forwarded = $this->getPastRequest(1);
        static::assertSame('https://example.local/moved', (string) $forwarded->getUri());
        static::assertSame('POST', $forwarded->getMethod());
        static::assertSame('test', $forwarded->getBody()->getContents());
        static::assertSame(
            hash_hmac('sha256', 'test', 'secret'),
            $forwarded->getHeaderLine(RequestSigner::CONTENA_SHOP_SIGNATURE)
        );
    }

    public function testMissingRequiredResponseHeader(): void
    {
        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');
        $client->post(new Uri('https://example.local'));

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertArrayNotHasKey(RequestSigner::CONTENA_SHOP_SIGNATURE, $request->getHeaders());
    }

    public function testIncorrectInstanceOfOptionRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $optionsRequest = [AuthMiddleware::APP_REQUEST_TYPE => new Response()];
        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');
        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testIncorrectAppContextInstanceOfOptionRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $optionsRequest = [AuthMiddleware::APP_REQUEST_CONTEXT => new Response()];
        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');
        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testInCorrectAuthenticResponse(): void
    {
        $this->expectException(ServerException::class);

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'secret',
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
                'body' => 'test', ];

        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testOptionRequestArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => 'Not Array',
                'body' => 'test', ];

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testOptionRequestMissingSecretArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->appendNewResponse(new Response(200));

        $client = static::getContainer()->get('contena.app_system.guzzle');

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
                'body' => 'test', ];

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }
}
