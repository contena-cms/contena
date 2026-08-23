<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(McpSessionIdValidator::class)]
class McpSessionIdValidatorTest extends TestCase
{
    public function testAcceptsMissingSessionHeader(): void
    {
        new McpSessionIdValidator()->validate(new Request());

        $this->addToAssertionCount(1);
    }

    public function testAcceptsUuidSessionHeader(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, Uuid::v7()->toRfc4122());

        new McpSessionIdValidator()->validate($request);

        $this->addToAssertionCount(1);
    }

    public function testRejectsMalformedSessionHeader(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, 'not-a-uuid');
        $this->expectExceptionObject(McpException::invalidSessionId());

        new McpSessionIdValidator()->validate($request);
    }
}
