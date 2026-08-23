<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Contena\Core\Framework\Mcp\McpAllowedHostsProvider;
use Contena\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

/**
 * @internal
 */
#[CoversClass(McpHttpTransportFactory::class)]
class McpHttpTransportFactoryTest extends TestCase
{
    public function testCreateResponseForcesEmptyToolPropertiesToAnObject(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"contena-toolsets-list","inputSchema":{"type":"object","properties":[]}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponseNormalizesToolPropertiesInsideAnEventStreamBatch(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"notifications/tools/list_changed"},'
            . '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"contena-toolsets-list","inputSchema":{"type":"object","properties":[]}}]}}]';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        static::assertStringContainsString('"properties":{}', (string) $response->getContent());
        static::assertStringNotContainsString('"properties":[]', (string) $response->getContent());
    }

    public function testCreateResponseNormalizesToolPropertiesInsideNativeEventStream(): void
    {
        $data = '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"extension-tool","inputSchema":{"type":"object","properties":[]}}]}}';
        $sse = "event: message\nid: 1\ndata: " . $data . "\n\n";

        $response = $this->factory()->createResponse($this->psrResponse('text/event-stream', $sse));

        $body = (string) $response->getContent();
        static::assertStringContainsString('"properties":{}', $body);
        static::assertStringNotContainsString('"properties":[]', $body);
        static::assertStringContainsString("event: message\nid: 1\ndata: ", $body);
    }

    public function testCreateResponseLeavesScalarEventStreamFramesUntouched(): void
    {
        $tools = '{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":[]}}]}}';
        $sse = "event: message\ndata: 42\n\nevent: message\ndata: " . $tools . "\n\n";

        $body = (string) $this->factory()->createResponse($this->psrResponse('text/event-stream', $sse))->getContent();

        static::assertStringContainsString('data: 42', $body);
        static::assertStringContainsString('"properties":{}', $body);
    }

    public function testCreateResponseNormalizesNestedAndOutputSchemaProperties(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":{"filter":{"type":"object","properties":[]}}},"outputSchema":{"type":"object","properties":[]}}]}}';

        $body = (string) $this->factory()->createResponse($this->psrResponse('application/json', $json))->getContent();

        static::assertStringNotContainsString('"properties":[]', $body);
        static::assertSame(2, substr_count($body, '"properties":{}'));
    }

    public function testCreateResponsePreservesHeadersWhenRewritingBody(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":[]}}]}}';
        $psrResponse = $this->psrResponse('application/json; charset=utf-8', $json)
            ->withHeader('X-Custom-Header', 'kept')
            ->withHeader(PlatformRequest::HEADER_MCP_SESSION_ID, 'session-123')
            ->withHeader('Content-Length', (string) \strlen($json));

        $response = $this->factory()->createResponse($psrResponse);

        static::assertSame('kept', $response->headers->get('X-Custom-Header'));
        static::assertSame('session-123', $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));
        static::assertStringContainsString('charset=utf-8', (string) $response->headers->get('Content-Type'));
        static::assertFalse($response->headers->has('Content-Length'));
    }

    public function testCreateResponseLeavesPopulatedToolPropertiesUntouched(): void
    {
        $json = '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"t","inputSchema":{"type":"object","properties":{"q":{"type":"string"}}}}]}}';

        $response = $this->factory()->createResponse($this->psrResponse('application/json', $json));

        static::assertSame($json, $response->getContent());
    }

    private function psrResponse(string $contentType, string $body): ResponseInterface
    {
        $psr17 = new Psr17Factory();

        return $psr17->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($psr17->createStream($body));
    }

    private function factory(): McpHttpTransportFactory
    {
        $psr17 = new Psr17Factory();

        return new McpHttpTransportFactory(
            static::createStub(HttpMessageFactoryInterface::class),
            $psr17,
            $psr17,
            new HttpFoundationFactory(),
            static::createStub(McpAllowedHostsProvider::class),
        );
    }
}
