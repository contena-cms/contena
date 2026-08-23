<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Mcp\McpAllowedHostsProvider;
use Contena\Core\Framework\Mcp\McpToolSchemaNormalizer;
use Contena\Core\Framework\Util\Json;
use Contena\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * HTTP glue for the Administration MCP controller: builds the Streamable HTTP
 * transport (seeded with the shop's own hosts for DNS-rebinding protection) and converts the SDK's
 * PSR response into a spec-compliant Symfony response.
 */
class McpHttpTransportFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly McpAllowedHostsProvider $allowedHostsProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function createTransport(Request $request): StreamableHttpTransport
    {
        return new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middleware(),
        );
    }

    public function createStream(string $content): StreamInterface
    {
        return $this->streamFactory->createStream($content);
    }

    /**
     * Converts the SDK's PSR response into a spec-compliant Symfony response.
     *
     * The MCP Streamable HTTP transport requires an application/json body to be a single JSON-RPC
     * object (JSON-RPC batching was removed in 2025-06-18). When the SDK drains more than one queued
     * outgoing message (e.g. a tools/list_changed notification alongside the response) it bundles
     * them as a top-level JSON array over application/json, which conformant clients cannot parse.
     * Re-emit such a batch as a text/event-stream where each JSON-RPC message is its own
     * single-object SSE event, which is where server-initiated notifications belong.
     *
     * This guards against mcp/sdk v0.6.0 (symfony/mcp-bundle v0.10.0) still emitting batch arrays.
     * Once the SDK stops bundling multiple messages over application/json, this becomes a no-op and
     * can be removed. Tool schemas are normalized on the JSON, converted SSE batch, and native SSE
     * paths so an empty `properties` map never reaches a client as `[]`.
     */
    public function createResponse(PsrResponseInterface $psrResponse): Response
    {
        $contentType = strtolower($psrResponse->getHeaderLine('Content-Type'));

        if (str_starts_with($contentType, 'text/event-stream')) {
            $body = (string) $psrResponse->getBody();

            return $this->rebuildResponse(self::normalizeEventStreamBody($body) ?? $body, $psrResponse);
        }

        if (str_starts_with($contentType, 'application/json')) {
            $decoded = json_decode((string) $psrResponse->getBody(), true);

            if (\is_array($decoded) && array_is_list($decoded) && $decoded !== []) {
                return $this->eventStreamResponse($decoded, $psrResponse);
            }

            if (\is_array($decoded)) {
                $normalized = McpToolSchemaNormalizer::normalizeToolListResult($decoded);

                if ($normalized !== null) {
                    return $this->rebuildResponse(Json::encode($normalized), $psrResponse);
                }
            }
        }

        return $this->httpFoundationFactory->createResponse($psrResponse, false);
    }

    private static function normalizeEventStreamBody(string $body): ?string
    {
        $lines = explode("\n", $body);
        $changed = false;

        foreach ($lines as $index => $line) {
            if (!str_starts_with($line, 'data:')) {
                continue;
            }

            $prefixLength = str_starts_with($line, 'data: ') ? 6 : 5;
            $decoded = json_decode(substr($line, $prefixLength), true);

            if (!\is_array($decoded)) {
                continue;
            }

            $normalized = McpToolSchemaNormalizer::normalizeToolListResult($decoded);

            if ($normalized === null) {
                continue;
            }

            $lines[$index] = substr($line, 0, $prefixLength) . Json::encode($normalized);
            $changed = true;
        }

        return $changed ? implode("\n", $lines) : null;
    }

    private function rebuildResponse(string $body, PsrResponseInterface $psrResponse): Response
    {
        $response = new Response($body, $psrResponse->getStatusCode());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $response->headers->set($name, $values);
        }

        $response->headers->remove('Content-Length');

        return $response;
    }

    /**
     * @param list<mixed> $messages
     */
    private function eventStreamResponse(array $messages, PsrResponseInterface $psrResponse): Response
    {
        $body = '';
        foreach ($messages as $message) {
            if (\is_array($message)) {
                $message = McpToolSchemaNormalizer::normalizeToolListResult($message) ?? $message;
            }

            $body .= 'event: message' . "\n" . 'data: ' . Json::encode($message) . "\n\n";
        }

        $response = new Response($body, $psrResponse->getStatusCode(), [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        $sessionId = $psrResponse->getHeaderLine(PlatformRequest::HEADER_MCP_SESSION_ID);
        if ($sessionId !== '') {
            $response->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);
        }

        return $response;
    }

    /**
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which rejects
     * every request that reaches Contena through its configured hostname. Keep the mitigation but
     * seed it with the platform's own host (APP_URL) so legitimate clients pass
     * while cross-origin rebinding attempts are still blocked.
     *
     * @return list<MiddlewareInterface>
     */
    private function middleware(): array
    {
        $allowedHosts = $this->allowedHostsProvider->getAllowedHosts();

        return array_map(
            static fn (MiddlewareInterface $middleware): MiddlewareInterface => $middleware instanceof DnsRebindingProtectionMiddleware
                ? new DnsRebindingProtectionMiddleware($allowedHosts)
                : $middleware,
            StreamableHttpTransport::defaultMiddleware(),
        );
    }
}
