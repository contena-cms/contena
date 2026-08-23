<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\Routing\Exception\ChannelMappingException;

/**
 * @internal
 */
#[CoversClass(ChannelMappingException::class)]
class ChannelMappingExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new ChannelMappingException('test');

        static::assertSame('Unable to find a matching channel for the request: "test". Please make sure the domain mapping is correct.', $exception->getMessage());
        static::assertSame('FRAMEWORK__INVALID_CHANNEL_MAPPING', $exception->getErrorCode());
        static::assertSame(404, $exception->getStatusCode());
    }
}
