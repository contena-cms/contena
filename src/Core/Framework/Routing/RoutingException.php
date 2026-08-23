<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Contena\Core\Framework\Routing\Exception\MemberNotLoggedInRoutingException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RoutingException extends HttpException
{
    public const string MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__MISSING_REQUEST_PARAMETER';
    public const string TENANT_SWITCH_FORBIDDEN_CODE = 'FRAMEWORK__TENANT_SWITCH_FORBIDDEN';
    public const string TENANT_DOMAIN_MISMATCH_CODE = 'FRAMEWORK__TENANT_DOMAIN_MISMATCH';
    public const string INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__INVALID_REQUEST_PARAMETER';
    public const string LANGUAGE_NOT_FOUND = 'FRAMEWORK__LANGUAGE_NOT_FOUND';
    public const string ACCESS_DENIED_FOR_XML_HTTP_REQUEST = 'FRAMEWORK__ACCESS_DENIED_FOR_XML_HTTP_REQUEST';
    public const string MISSING_PRIVILEGE = 'FRAMEWORK__ROUTING_MISSING_PRIVILEGE';
    public const string INVALID_ROUTE_SCOPE = 'FRAMEWORK__ROUTING_INVALID_ROUTE_SCOPE';
    public const string MISSING_MAIN_REQUEST = 'FRAMEWORK__MAIN_REQUEST_MISSING';
    public const string MISSING_ROUTE_ATTRIBUTE = 'FRAMEWORK__ROUTING_ROUTE_ATTRIBUTE_MISSING';
    public const string CHANNEL_DOMAIN_NOT_FOUND = 'FRAMEWORK__ROUTING_CHANNEL_DOMAIN_NOT_FOUND';
    public const string CHANNEL_MEMBER_NOT_LOGGED_IN = 'FRAMEWORK__ROUTING_CHANNEL_MEMBER_NOT_LOGGED_IN';

    public static function channelDomainNotFound(string $domainUrl): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_DOMAIN_NOT_FOUND,
            'The domain "{{ domainUrl }}" provided via the "' . PlatformRequest::HEADER_DOMAIN . '" header is not a configured domain of this channel.',
            ['domainUrl' => $domainUrl]
        );
    }

    public static function channelMemberNotLoggedIn(): MemberNotLoggedInRoutingException
    {
        return new MemberNotLoggedInRoutingException(
            Response::HTTP_FORBIDDEN,
            self::CHANNEL_MEMBER_NOT_LOGGED_IN,
            'A logged-in member is required for this operation.',
        );
    }

    public static function invalidRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function tenantSwitchForbidden(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::TENANT_SWITCH_FORBIDDEN_CODE,
            'Tenant users can not switch into another tenant.',
        );
    }

    public static function tenantDomainMismatch(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::TENANT_DOMAIN_MISMATCH_CODE,
            'The tenant of the current domain does not match the user\'s tenant.',
        );
    }

    public static function languageNotFound(?string $languageId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId]
        );
    }

    public static function accessDeniedForXmlHttpRequest(?string $route = null, ?string $url = null, ?string $referer = null): self
    {
        $message = 'PageController ' . ($route ? '"{{ route }}" ' : '')
            . ($url ? '("{{ url }}") ' : '')
            . 'can\'t be requested via XmlHttpRequest.'
            . ($referer ? ' Requested by "{{ referer }}".' : '');

        return new self(
            Response::HTTP_FORBIDDEN,
            self::ACCESS_DENIED_FOR_XML_HTTP_REQUEST,
            $message,
            ['route' => $route, 'url' => $url, 'referer' => $referer]
        );
    }

    /**
     * @param string[] $privileges
     */
    public static function missingPrivileges(array $privileges): self
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privileges,
        ], \JSON_THROW_ON_ERROR);

        return new self(
            Response::HTTP_FORBIDDEN,
            self::MISSING_PRIVILEGE,
            $errorMessage ?: ''
        );
    }

    public static function unexpectedType(mixed $actualType, string $expectedType): UnexpectedTypeException
    {
        return new UnexpectedTypeException($actualType, $expectedType);
    }

    public static function invalidRouteScope(?string $routeName): self
    {
        return new InvalidRouteScopeException($routeName);
    }

    public static function missingMainRequest(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_MAIN_REQUEST,
            'Unable to check the request scope without main request.'
        );
    }

    public static function missingRouteAttribute(string $routeAttribute, string $route): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_ROUTE_ATTRIBUTE,
            'Route attribute "{{ routeAttribute }}" on route "{{ route }}" is missing.',
            ['routeAttribute' => $routeAttribute, 'route' => $route],
        );
    }
}
