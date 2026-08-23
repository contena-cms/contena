<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal
 */
#[CoversClass(RoutingException::class)]
class RoutingExceptionTest extends TestCase
{
    public function testInvalidRequestParameter(): void
    {
        $e = RoutingException::invalidRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::INVALID_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    public function testMissingRequestParameter(): void
    {
        $e = RoutingException::missingRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::MISSING_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    public function testLanguageNotFound(): void
    {
        $e = RoutingException::languageNotFound('foo');

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $e->getStatusCode());
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $e->getErrorCode());
    }

    public function testAccessDeniedForXmlHttpRequest(): void
    {
        $e = RoutingException::accessDeniedForXmlHttpRequest();

        static::assertSame(RoutingException::class, $e::class);
        static::assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
        static::assertSame(RoutingException::ACCESS_DENIED_FOR_XML_HTTP_REQUEST, $e->getErrorCode());
    }

    public function testMissingPrivileges(): void
    {
        $privileges = ['media:read', 'tag:write'];
        $e = RoutingException::missingPrivileges($privileges);

        static::assertSame(RoutingException::class, $e::class);
        static::assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
        static::assertSame(RoutingException::MISSING_PRIVILEGE, $e->getErrorCode());

        // The message should be a JSON string containing the privileges
        $decodedMessage = json_decode($e->getMessage(), true);
        static::assertIsArray($decodedMessage);
        static::assertArrayHasKey('message', $decodedMessage);
        static::assertArrayHasKey('missingPrivileges', $decodedMessage);
        static::assertSame('Missing privilege', $decodedMessage['message']);
        static::assertSame($privileges, $decodedMessage['missingPrivileges']);
    }

    public function testUnexpectedTypeException(): void
    {
        $e = RoutingException::unexpectedType(2, 'valid');

        static::assertSame(UnexpectedTypeException::class, $e::class);
        static::assertSame('Expected argument of type "valid", "int" given', $e->getMessage());
    }
}
