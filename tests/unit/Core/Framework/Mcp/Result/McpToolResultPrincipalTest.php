<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Result\McpToolResultPrincipal;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(McpToolResultPrincipal::class)]
class McpToolResultPrincipalTest extends TestCase
{
    public function testAnIntegrationAndItsUserNameTheAdminPrincipal(): void
    {
        static::assertSame('admin:integration-id:admin_user-id', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource('admin_user-id', 'integration-id'))));
        static::assertSame('admin:integration-id:', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource(null, 'integration-id'))));
        static::assertSame('admin::admin_user-id', McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource('admin_user-id'))));
    }

    public function testTheChannelAndContextTokenNameTheStorePrincipal(): void
    {
        $channelContext = Generator::generateChannelContext(token: 'context-token');

        $request = new Request(server: ['HTTP_SW_CONTEXT_TOKEN' => 'context-token']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $channelContext->getContext());

        static::assertSame('store:' . $channelContext->getChannelId() . ':context-token', McpToolResultPrincipal::fromRequest($request));
    }

    public function testAStoreCallWithoutAContextTokenHasNoPrincipal(): void
    {
        // The context resolver puts a random token into the headers of such a request; the client never sees it.
        $channelContext = Generator::generateChannelContext(token: 'minted-for-this-request');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'minted-for-this-request');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        static::assertNull(McpToolResultPrincipal::fromRequest($request));
    }

    public function testThereIsNoPrincipalWithoutAnAuthenticatedCaller(): void
    {
        static::assertNull(McpToolResultPrincipal::fromRequest(new Request()));
        static::assertNull(McpToolResultPrincipal::fromRequest($this->adminRequest(new SystemSource())));
        static::assertNull(McpToolResultPrincipal::fromRequest($this->adminRequest(new AdminApiSource(null))));
    }

    private function adminRequest(AdminApiSource|SystemSource $source): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));

        return $request;
    }
}
