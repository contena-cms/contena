<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Member\Exception\AddressNotFoundException;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\Exception\InvalidImitateMemberTokenException;
use Contena\Core\System\Member\Exception\MemberAlreadyConfirmedException;
use Contena\Core\System\Member\Exception\MemberAuthThrottledException;
use Contena\Core\System\Member\Exception\MemberNotFoundByHashException;
use Contena\Core\System\Member\Exception\MemberNotFoundByIdException;
use Contena\Core\System\Member\Exception\MemberNotFoundException;
use Contena\Core\System\Member\Exception\MemberOptinNotCompletedException;
use Contena\Core\System\Member\Exception\MemberRecoveryHashExpiredException;
use Symfony\Component\HttpFoundation\Response;

class MemberException extends HttpException
{
    final public const string MEMBERS_NOT_FOUND = 'SYSTEM__MEMBERS_NOT_FOUND';
    final public const string MEMBER_NOT_FOUND = 'SYSTEM__MEMBER_NOT_FOUND';
    final public const string MEMBER_NOT_FOUND_BY_ID = 'SYSTEM__MEMBER_NOT_FOUND_BY_ID';
    final public const string MEMBER_GROUP_NOT_FOUND = 'SYSTEM__MEMBER_GROUP_NOT_FOUND';
    final public const string MEMBER_GROUP_REQUEST_NOT_FOUND = 'SYSTEM__MEMBER_GROUP_REQUEST_NOT_FOUND';
    final public const string MEMBER_IDS_PARAMETER_IS_MISSING = 'SYSTEM__MEMBER_IDS_PARAMETER_IS_MISSING';
    final public const string MEMBER_AUTH_BAD_CREDENTIALS = 'SYSTEM__MEMBER_AUTH_BAD_CREDENTIALS';
    final public const string MEMBER_AUTH_THROTTLED = 'SYSTEM__MEMBER_AUTH_THROTTLED';
    final public const string MEMBER_OPTIN_NOT_COMPLETED = 'SYSTEM__MEMBER_OPTIN_NOT_COMPLETED';
    final public const string MEMBER_ADDRESS_NOT_FOUND = 'SYSTEM__MEMBER_ADDRESS_NOT_FOUND';
    final public const string MEMBER_ALREADY_CONFIRMED = 'SYSTEM__MEMBER_ALREADY_CONFIRMED';
    final public const string MEMBER_GROUP_REGISTRATION_NOT_FOUND = 'SYSTEM__MEMBER_GROUP_REGISTRATION_NOT_FOUND';
    final public const string MEMBER_NOT_FOUND_BY_HASH = 'SYSTEM__MEMBER_NOT_FOUND_BY_HASH';
    final public const string MEMBER_RECOVERY_HASH_EXPIRED = 'SYSTEM__MEMBER_RECOVERY_HASH_EXPIRED';
    final public const string IMITATE_MEMBER_INVALID_TOKEN = 'SYSTEM__IMITATE_MEMBER_INVALID_TOKEN';
    final public const string NO_HASH_PROVIDED = 'SYSTEM__NO_HASH_PROVIDED';
    final public const string MISSING_ROUTE_ANNOTATION = 'SYSTEM__MISSING_ROUTE_ANNOTATION';
    final public const string MISSING_ROUTE_CHANNEL = 'SYSTEM__MISSING_ROUTE_CHANNEL';
    final public const string OPERATOR_NOT_SUPPORTED = 'SYSTEM__MEMBER_RULE_OPERATOR_NOT_SUPPORTED';
    final public const string VALUE_NOT_SUPPORTED = 'SYSTEM__MEMBER_RULE_VALUE_NOT_SUPPORTED';
    final public const string UNEXPECTED_TYPE = 'SYSTEM__UNEXPECTED_TYPE';
    final public const string COUNTRY_NOT_FOUND = 'SYSTEM__MEMBER_COUNTRY_NOT_FOUND';

    public static function badCredentials(): BadCredentialsException
    {
        return new BadCredentialsException();
    }

    public static function memberNotFoundById(string $id): MemberNotFoundByIdException
    {
        return new MemberNotFoundByIdException($id);
    }

    public static function memberNotFound(string $email): MemberNotFoundException
    {
        return new MemberNotFoundException($email);
    }

    public static function memberGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEMBER_GROUP_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'member group', 'field' => 'id', 'value' => $id],
        );
    }

    public static function groupRequestNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEMBER_GROUP_REQUEST_NOT_FOUND,
            'Group request for member "{{ id }}" is not found',
            ['id' => $id],
        );
    }

    /**
     * @param string[] $ids
     */
    public static function membersNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEMBERS_NOT_FOUND,
            'These members "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)],
        );
    }

    public static function memberIdsParameterIsMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEMBER_IDS_PARAMETER_IS_MISSING,
            'Parameter "memberIds" is missing.',
        );
    }

    public static function memberOptinNotCompleted(string $id): MemberOptinNotCompletedException
    {
        return new MemberOptinNotCompletedException($id);
    }

    public static function memberAuthThrottled(int $waitTime, ?\Throwable $exception = null): MemberAuthThrottledException
    {
        return new MemberAuthThrottledException($waitTime, $exception);
    }

    public static function addressNotFound(string $id): AddressNotFoundException
    {
        return new AddressNotFoundException($id);
    }

    public static function memberAlreadyConfirmed(string $id): MemberAlreadyConfirmedException
    {
        return new MemberAlreadyConfirmedException($id);
    }

    public static function memberGroupRegistrationConfigurationNotFound(string $memberGroupId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MEMBER_GROUP_REGISTRATION_NOT_FOUND,
            'Member group registration for id {{ memberGroupId }} not found.',
            ['memberGroupId' => $memberGroupId],
        );
    }

    public static function memberNotFoundByHash(string $hash): MemberNotFoundByHashException
    {
        return new MemberNotFoundByHashException($hash);
    }

    public static function memberRecoveryHashExpired(string $hash): MemberRecoveryHashExpiredException
    {
        return new MemberRecoveryHashExpiredException($hash);
    }

    public static function invalidImitationToken(string $token): InvalidImitateMemberTokenException
    {
        return new InvalidImitateMemberTokenException($token);
    }

    public static function noHashProvided(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NO_HASH_PROVIDED,
            'The given hash is empty.',
        );
    }

    public static function missingRouteAnnotation(string $annotation, string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ROUTE_ANNOTATION,
            'Missing @{{ annotation }} annotation for route: {{ route }}',
            ['annotation' => $annotation, 'route' => $route],
        );
    }

    public static function missingRouteChannel(string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ROUTE_CHANNEL,
            'Missing channel context for route {{ route }}',
            ['route' => $route],
        );
    }

    public static function unsupportedOperator(string $operator, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class],
        );
    }

    public static function unsupportedValue(string $type, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class],
        );
    }

    public static function unexpectedType(object $value, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNEXPECTED_TYPE,
            'Expected argument of type "{{ expectedType }}", "{{ givenType }}" given',
            ['expectedType' => $class, 'givenType' => get_debug_type($value)],
        );
    }

    public static function countryNotFound(string $countryId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            'Country with id "{{ countryId }}" not found.',
            ['countryId' => $countryId],
        );
    }
}
