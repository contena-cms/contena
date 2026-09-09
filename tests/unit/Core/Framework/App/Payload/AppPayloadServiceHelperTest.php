<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Payload;

use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\App\Payload\SourcedPayloadInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(AppPayloadServiceHelper::class)]
class AppPayloadServiceHelperTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testBuildSource(): void
    {
        $installationId = InstallationId::create($this->ids->get('installation-id'));
        $installationIdProvider = static::createStub(InstallationIdProvider::class);
        $installationIdProvider
            ->method('getInstallationId')
            ->willReturn($installationId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(JsonEntityEncoder::class),
            $installationIdProvider,
            'https://contena.com',
            new MockClock(),
        );

        $source = $appPayloadServiceHelper->buildSource('1.0.0', 'TestApp');

        static::assertSame('https://contena.com', $source->getUrl());
        static::assertSame($this->ids->get('installation-id'), $source->getInstallationId());
        static::assertSame('1.0.0', $source->getAppVersion());
    }

    public function testCreateRequestOptionsWithNoParams(): void
    {
        $installationId = InstallationId::create($this->ids->get('installation-id'));
        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $entityEncoder = static::createStub(JsonEntityEncoder::class);
        $installationIdProvider = static::createStub(InstallationIdProvider::class);
        $installationIdProvider
            ->method('getInstallationId')
            ->willReturn($installationId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $installationIdProvider,
            'https://contena.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('top-secret');

        $payload = $this->createMock(SourcedPayloadInterface::class);
        $payload
            ->expects($this->once())
            ->method('setSource')
            ->with(static::isInstanceOf(Source::class));
        $payload
            ->method('jsonSerialize')
            ->willReturn(['key' => 'value']);

        $jsonPayload = $appPayloadServiceHelper->createRequestOptions($payload, $app, $context)->jsonSerialize();

        static::assertSame($context, $jsonPayload[AuthMiddleware::APP_REQUEST_CONTEXT]);
        static::assertSame('top-secret', $jsonPayload[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
        static::assertSame(['Content-Type' => 'application/json'], $jsonPayload['headers']);
        static::assertJsonStringEqualsJsonString('{"key":"value"}', $jsonPayload['body']);
    }

    public function testCreateRequestOptionsWithAdditionalParams(): void
    {
        $installationId = InstallationId::create($this->ids->get('installation-id'));
        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $entityEncoder = static::createStub(JsonEntityEncoder::class);
        $installationIdProvider = static::createStub(InstallationIdProvider::class);
        $installationIdProvider
            ->method('getInstallationId')
            ->willReturn($installationId);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $installationIdProvider,
            'https://contena.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setAppSecret('top-secret');

        $payload = $this->createMock(SourcedPayloadInterface::class);
        $payload
            ->expects($this->once())
            ->method('setSource')
            ->with(static::isInstanceOf(Source::class));
        $payload
            ->method('jsonSerialize')
            ->willReturn(['key' => 'value']);

        $jsonPayload = $appPayloadServiceHelper->createRequestOptions($payload, $app, $context, ['timeout' => 50])->jsonSerialize();

        static::assertSame($context, $jsonPayload[AuthMiddleware::APP_REQUEST_CONTEXT]);
        static::assertSame('top-secret', $jsonPayload[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
        static::assertSame(['Content-Type' => 'application/json'], $jsonPayload['headers']);
        static::assertArrayHasKey('timeout', $jsonPayload);
        static::assertSame(50, $jsonPayload['timeout']);
        static::assertJsonStringEqualsJsonString('{"key":"value"}', $jsonPayload['body']);
    }

    public function testCreateRequestOptionsThrowsExceptionWhenNoAppSecret(): void
    {
        $this->expectExceptionObject(AppException::registrationFailed('TestApp', 'App secret is missing'));

        $context = Context::createDefaultContext();
        $definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $entityEncoder = static::createStub(JsonEntityEncoder::class);
        $installationIdProvider = static::createStub(InstallationIdProvider::class);

        $appPayloadServiceHelper = new AppPayloadServiceHelper(
            $definitionInstanceRegistry,
            $entityEncoder,
            $installationIdProvider,
            'https://contena.com',
            new MockClock(),
        );

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId($this->ids->get('app'));
        $app->setVersion('1.0.0');
        $app->setName('TestApp');

        $payload = static::createStub(SourcedPayloadInterface::class);

        $appPayloadServiceHelper->createRequestOptions($payload, $app, $context);
    }

    public function testCreateWebhookRequestWithAllParams(): void
    {
        $clock = new MockClock('2026-01-15 12:00:00');
        $helper = $this->createHelper($clock);

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
            'my-secret',
            'lang-id-123',
            'en-GB',
            ['X-Custom' => 'header-val'],
        );

        $body = json_decode($result->body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('value', $body['data']);
        static::assertSame($clock->now()->getTimestamp(), $body['timestamp']);
        static::assertSame($clock->now()->getTimestamp(), $result->timestamp);

        // Headers
        static::assertSame('application/json', $result->headers['Content-Type']);
        static::assertSame('6.7.0', $result->headers['ct-version']);
        static::assertSame('header-val', $result->headers['X-Custom']);
        static::assertSame('lang-id-123', $result->headers[AuthMiddleware::CONTENA_CONTEXT_LANGUAGE]);
        static::assertSame('en-GB', $result->headers[AuthMiddleware::CONTENA_USER_LANGUAGE]);

        // PSR-7 request
        static::assertSame('POST', $result->request->getMethod());
        static::assertSame('https://hook.example.com', (string) $result->request->getUri());

        // Options with secret
        static::assertSame(10, $result->options['connect_timeout']);
        static::assertSame(20, $result->options['timeout']);
        static::assertSame('my-secret', $result->options[AuthMiddleware::APP_REQUEST_TYPE][AuthMiddleware::APP_SECRET]);
    }

    public function testCreateWebhookRequestWithoutSecret(): void
    {
        $helper = $this->createHelper(new MockClock());

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
        );

        static::assertArrayNotHasKey(AuthMiddleware::APP_REQUEST_TYPE, $result->options);
        static::assertSame(10, $result->options['connect_timeout']);
        static::assertSame(20, $result->options['timeout']);
    }

    public function testCreateWebhookRequestWithoutLanguageHeaders(): void
    {
        $helper = $this->createHelper(new MockClock());

        $result = $helper->createWebhookRequest(
            ['data' => 'value'],
            'https://hook.example.com',
            '6.7.0',
            10,
            20,
        );

        static::assertArrayNotHasKey(AuthMiddleware::CONTENA_CONTEXT_LANGUAGE, $result->headers);
        static::assertArrayNotHasKey(AuthMiddleware::CONTENA_USER_LANGUAGE, $result->headers);
    }

    private function createHelper(MockClock $clock): AppPayloadServiceHelper
    {
        return new AppPayloadServiceHelper(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(JsonEntityEncoder::class),
            static::createStub(InstallationIdProvider::class),
            'https://contena.com',
            $clock,
        );
    }
}
