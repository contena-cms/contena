<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(MemberException::class)]
class MemberExceptionTest extends TestCase
{
    public function testMemberGroupNotFound(): void
    {
        $exception = MemberException::memberGroupNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_GROUP_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find member group with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'member group', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testGroupRequestNotFound(): void
    {
        $exception = MemberException::groupRequestNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_GROUP_REQUEST_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Group request for member "id-1" is not found', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testMembersNotFound(): void
    {
        $exception = MemberException::membersNotFound(['id-1', 'id-2']);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBERS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('These members "id-1, id-2" are not found', $exception->getMessage());
        static::assertSame(['ids' => 'id-1, id-2'], $exception->getParameters());
    }

    public function testMemberIdsParameterIsMissing(): void
    {
        $exception = MemberException::memberIdsParameterIsMissing();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_IDS_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "memberIds" is missing.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testAddressNotFound(): void
    {
        $exception = MemberException::addressNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_ADDRESS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Member address with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testBadCredentials(): void
    {
        $exception = MemberException::badCredentials();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_AUTH_BAD_CREDENTIALS, $exception->getErrorCode());
        static::assertSame('Invalid username and/or password.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testMemberAlreadyConfirmed(): void
    {
        $exception = MemberException::memberAlreadyConfirmed('id-1');
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_ALREADY_CONFIRMED, $exception->getErrorCode());
        static::assertSame('The member with the id "id-1" is already confirmed.', $exception->getMessage());
        static::assertSame(['memberId' => 'id-1'], $exception->getParameters());
    }

    public function testMemberGroupRegistrationConfigurationNotFound(): void
    {
        $exception = MemberException::memberGroupRegistrationConfigurationNotFound('id-1');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_GROUP_REGISTRATION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Member group registration for id id-1 not found.', $exception->getMessage());
        static::assertSame(['memberGroupId' => 'id-1'], $exception->getParameters());
    }

    public function testMemberNotFoundByHash(): void
    {
        $exception = MemberException::memberNotFoundByHash('e9c8985e0b0f8ec20a16ac9ffd0m');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_NOT_FOUND_BY_HASH, $exception->getErrorCode());
        static::assertSame('No matching member for the hash "e9c8985e0b0f8ec20a16ac9ffd0m" was found.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testMemberNotFoundById(): void
    {
        $exception = MemberException::memberNotFoundById('id-1');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_NOT_FOUND_BY_ID, $exception->getErrorCode());
        static::assertSame('No matching member for the id "id-1" was found.', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testMemberNotFound(): void
    {
        $exception = MemberException::memberNotFound('abc@com');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No matching member for the email "abc@com" was found.', $exception->getMessage());
        static::assertSame(['email' => 'abc@com'], $exception->getParameters());
    }

    public function testMemberRecoveryHashExpired(): void
    {
        $exception = MemberException::memberRecoveryHashExpired('e9c8985e0b0f8ec20a16ac9ffd0m');
        static::assertSame(Response::HTTP_GONE, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_RECOVERY_HASH_EXPIRED, $exception->getErrorCode());
        static::assertSame('The hash "e9c8985e0b0f8ec20a16ac9ffd0m" is expired.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testInvalidImitationToken(): void
    {
        $exception = MemberException::invalidImitationToken('token');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::IMITATE_MEMBER_INVALID_TOKEN, $exception->getErrorCode());
        static::assertSame('The token "token" is invalid.', $exception->getMessage());
        static::assertSame(['token' => 'token'], $exception->getParameters());
    }

    public function testNoHashProvided(): void
    {
        $exception = MemberException::noHashProvided();
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(MemberException::NO_HASH_PROVIDED, $exception->getErrorCode());
        static::assertSame('The given hash is empty.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testMemberOptinNotCompleted(): void
    {
        $exception = MemberException::memberOptinNotCompleted('id-1');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_OPTIN_NOT_COMPLETED, $exception->getErrorCode());
        static::assertSame('The member with the id "id-1" has not completed the opt-in.', $exception->getMessage());
        static::assertSame(['memberId' => 'id-1'], $exception->getParameters());
        static::assertSame('account.doubleOptinAccountAlert', $exception->getSnippetKey());
    }

    public function testMemberAuthThrottled(): void
    {
        $exception = MemberException::memberAuthThrottled(100);
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame(MemberException::MEMBER_AUTH_THROTTLED, $exception->getErrorCode());
        static::assertSame('Member auth throttled for 100 seconds.', $exception->getMessage());
        static::assertSame(100, $exception->getWaitTime());
    }

    public function testCountryNotFound(): void
    {
        $exception = MemberException::countryNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::COUNTRY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Country with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['countryId' => 'id-1'], $exception->getParameters());
    }

    public function testUnsupportedOperator(): void
    {
        $exception = MemberException::unsupportedOperator('$', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::OPERATOR_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $exception->getMessage());
        static::assertSame(['operator' => '$', 'class' => 'testClass'], $exception->getParameters());
    }

    public function testUnsupportedValue(): void
    {
        $exception = MemberException::unsupportedValue('badType', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(MemberException::VALUE_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('Unsupported value of type badType in testClass', $exception->getMessage());
        static::assertSame(['type' => 'badType', 'class' => 'testClass'], $exception->getParameters());
    }

    public function testMissingRouteAnnotation(): void
    {
        $exception = MemberException::missingRouteAnnotation('ChannelContextToken', 'frontend.home.page');
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MemberException::MISSING_ROUTE_ANNOTATION, $exception->getErrorCode());
        static::assertSame('Missing @ChannelContextToken annotation for route: frontend.home.page', $exception->getMessage());
        static::assertSame(['annotation' => 'ChannelContextToken', 'route' => 'frontend.home.page'], $exception->getParameters());
    }

    public function testMissingRouteChannel(): void
    {
        $exception = MemberException::missingRouteChannel('frontend.home.page');
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MemberException::MISSING_ROUTE_CHANNEL, $exception->getErrorCode());
        static::assertSame('Missing channel context for route frontend.home.page', $exception->getMessage());
        static::assertSame(['route' => 'frontend.home.page'], $exception->getParameters());
    }

    public function testUnexpectedType(): void
    {
        $exception = MemberException::unexpectedType(new \stdClass(), \ArrayObject::class);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(MemberException::UNEXPECTED_TYPE, $exception->getErrorCode());
        static::assertSame('Expected argument of type "ArrayObject", "stdClass" given', $exception->getMessage());
        static::assertSame(['expectedType' => \ArrayObject::class, 'givenType' => 'stdClass'], $exception->getParameters());
    }
}
