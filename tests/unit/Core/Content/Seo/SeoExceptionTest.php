<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Framework\Api\Exception\InvalidChannelIdException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SeoException::class)]
class SeoExceptionTest extends TestCase
{
    public function testInvalidChannelId(): void
    {
        $channelId = 'invalid-channel-id';

        $exception = SeoException::invalidChannelId($channelId);

        static::assertInstanceOf(InvalidChannelIdException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testChannelIdParameterIsMissing(): void
    {
        $exception = SeoException::channelIdParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::CHANNEL_ID_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "channelId" is missing.', $exception->getMessage());
    }

    public function testTemplateParameterIsMissing(): void
    {
        $exception = SeoException::templateParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::TEMPLATE_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "template" is missing.', $exception->getMessage());
    }

    public function testEntityNameParameterIsMissing(): void
    {
        $exception = SeoException::entityNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ENTITY_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "entityName" is missing.', $exception->getMessage());
    }

    public function testRouteNameParameterIsMissing(): void
    {
        $exception = SeoException::routeNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ROUTE_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "routeName" is missing.', $exception->getMessage());
    }

    public function testChannelNotFound(): void
    {
        $channelId = 'not-found-channel-id';

        $exception = SeoException::channelNotFound($channelId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(SeoException::CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find channel with id "not-found-channel-id"', $exception->getMessage());
        static::assertSame($channelId, $exception->getParameters()['value']);
    }
}
