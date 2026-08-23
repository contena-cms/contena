<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ContextTokenResponse;

/**
 * @internal
 */
#[CoversClass(ContextTokenResponse::class)]
class ContextTokenResponseTest extends TestCase
{
    public function testGetTokenFromResponseBody(): void
    {
        $token = 'ct-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());
    }

    public function testGetTokenFromHeader(): void
    {
        $token = 'ct-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());

        // It should be stored in a header instead
        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
