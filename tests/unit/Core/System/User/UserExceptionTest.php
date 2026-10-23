<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UserException::class)]
class UserExceptionTest extends TestCase
{
    public function testUserGroupNotFound(): void
    {
        $exception = UserException::userGroupNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_GROUP_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find user group with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'user group', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testGroupRequestNotFound(): void
    {
        $exception = UserException::groupRequestNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_GROUP_REQUEST_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Group request for user "id-1" is not found', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testUsersNotFound(): void
    {
        $exception = UserException::usersNotFound(['id-1', 'id-2']);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMERS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('These users "id-1, id-2" are not found', $exception->getMessage());
        static::assertSame(['ids' => 'id-1, id-2'], $exception->getParameters());
    }

    public function testUserNotLoggedIn(): void
    {
        $exception = UserException::userNotLoggedIn();
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_NOT_LOGGED_IN, $exception->getErrorCode());
        static::assertSame('User is not logged in.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testDownloadFileNotFound(): void
    {
        $exception = UserException::downloadFileNotFound('id-1');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Line item download file with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['downloadId' => 'id-1'], $exception->getParameters());
    }

    public function testUserIdsParameterIsMissing(): void
    {
        $exception = UserException::userIdsParameterIsMissing();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_IDS_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "userIds" is missing.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testUnknownPaymentMethod(): void
    {
        $exception = UserException::unknownPaymentMethod('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_CHANGE_PAYMENT_ERROR, $exception->getErrorCode());
        static::assertSame('Change Payment to method id-1 not possible.', $exception->getMessage());
        static::assertSame(['paymentMethodId' => 'id-1'], $exception->getParameters());
    }

    public function testBlogIdsParameterIsMissing(): void
    {
        $exception = UserException::blogIdsParameterIsMissing();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::BLOG_IDS_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "blogIds" is missing.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testAddressNotFound(): void
    {
        $exception = UserException::addressNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_ADDRESS_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('User address with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testBadCredentials(): void
    {
        $exception = UserException::badCredentials();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_AUTH_BAD_CREDENTIALS, $exception->getErrorCode());
        static::assertSame('Invalid username and/or password.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCannotDeleteActiveAddress(): void
    {
        $exception = UserException::cannotDeleteActiveAddress('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_ADDRESS_IS_ACTIVE, $exception->getErrorCode());
        static::assertSame('User address with id "id-1" is an active address and cannot be deleted.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testCannotDeleteDefaultAddress(): void
    {
        $exception = UserException::cannotDeleteDefaultAddress('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_ADDRESS_IS_DEFAULT, $exception->getErrorCode());
        static::assertSame('User address with id "id-1" is a default address and cannot be deleted.', $exception->getMessage());
        static::assertSame(['addressId' => 'id-1'], $exception->getParameters());
    }

    public function testUserAlreadyConfirmed(): void
    {
        $exception = UserException::userAlreadyConfirmed('id-1');
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_IS_ALREADY_CONFIRMED, $exception->getErrorCode());
        static::assertSame('The user with the id "id-1" is already confirmed.', $exception->getMessage());
        static::assertSame(['userId' => 'id-1'], $exception->getParameters());
    }

    public function testUserGroupRegistrationConfigurationNotFound(): void
    {
        $exception = UserException::userGroupRegistrationConfigurationNotFound('id-1');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('User group registration for id id-1 not found.', $exception->getMessage());
        static::assertSame(['userGroupId' => 'id-1'], $exception->getParameters());
    }

    public function testUserNotFoundByHash(): void
    {
        $exception = UserException::userNotFoundByHash('e9c8985e0b0f8ec20a16ac9ffd0m');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_NOT_FOUND_BY_HASH, $exception->getErrorCode());
        static::assertSame('No matching user for the hash "e9c8985e0b0f8ec20a16ac9ffd0m" was found.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testUserNotFoundByIdException(): void
    {
        $exception = UserException::userNotFoundByIdException('id-1');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_NOT_FOUND_BY_ID, $exception->getErrorCode());
        static::assertSame('No matching user for the id "id-1" was found.', $exception->getMessage());
        static::assertSame(['id' => 'id-1'], $exception->getParameters());
    }

    public function testUserNotFound(): void
    {
        $exception = UserException::userNotFound('abc@com');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No matching user for the email "abc@com" was found.', $exception->getMessage());
        static::assertSame(['email' => 'abc@com'], $exception->getParameters());
    }

    public function testUserRecoveryHashExpired(): void
    {
        $exception = UserException::userRecoveryHashExpired('e9c8985e0b0f8ec20a16ac9ffd0m');
        static::assertSame(Response::HTTP_GONE, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_RECOVERY_HASH_EXPIRED, $exception->getErrorCode());
        static::assertSame('The hash "e9c8985e0b0f8ec20a16ac9ffd0m" is expired.', $exception->getMessage());
        static::assertSame(['hash' => 'e9c8985e0b0f8ec20a16ac9ffd0m'], $exception->getParameters());
    }

    public function testUserWishlistNotActivated(): void
    {
        $exception = UserException::userWishlistNotActivated();
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(UserException::WISHLIST_IS_NOT_ACTIVATED, $exception->getErrorCode());
        static::assertSame('Wishlist is not activated!', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testUserWishlistNotFound(): void
    {
        $exception = UserException::userWishlistNotFound();
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::WISHLIST_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Wishlist for this user was not found.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testDuplicateWishlistBlog(): void
    {
        $exception = UserException::duplicateWishlistBlog();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::DUPLICATE_WISHLIST_BLOG, $exception->getErrorCode());
        static::assertSame('Blog already added in wishlist', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testLegacyPasswordEncoderNotFound(): void
    {
        $exception = UserException::legacyPasswordEncoderNotFound('encoder');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::LEGACY_PASSWORD_ENCODER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find encoder with name "encoder"', $exception->getMessage());
        static::assertSame(['entity' => 'encoder', 'field' => 'name', 'value' => 'encoder'], $exception->getParameters());
    }

    public function testNoHashProvided(): void
    {
        $exception = UserException::noHashProvided();
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::NO_HASH_PROVIDED, $exception->getErrorCode());
        static::assertSame('The given hash is empty.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testWishlistBlogNotFound(): void
    {
        $exception = UserException::wishlistBlogNotFound('id-1');
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(UserException::WISHLIST_BLOG_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find wishlist blog with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'wishlist blog', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }

    public function testUserOptinNotCompleted(): void
    {
        $exception = UserException::userOptinNotCompleted('id-1');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_OPTIN_NOT_COMPLETED, $exception->getErrorCode());
        static::assertSame('The user with the id "id-1" has not completed the opt-in.', $exception->getMessage());
        static::assertSame(['userId' => 'id-1'], $exception->getParameters());
        static::assertSame('account.doubleOptinAccountAlert', $exception->getSnippetKey());
    }

    public function testUserAuthThrottledException(): void
    {
        $exception = UserException::userAuthThrottledException(100);
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_AUTH_THROTTLED, $exception->getErrorCode());
        static::assertSame('User auth throttled for 100 seconds.', $exception->getMessage());
        static::assertSame(100, $exception->getWaitTime());
    }

    public function testGuestAccountInvalidAuth(): void
    {
        $exception = UserException::guestAccountInvalidAuth();
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        static::assertSame(UserException::CUSTOMER_GUEST_AUTH_INVALID, $exception->getErrorCode());
        static::assertSame('Guest account is not allowed to login', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testCountryNotFound(): void
    {
        $exception = UserException::countryNotFound('id-1');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::COUNTRY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Country with id "id-1" not found.', $exception->getMessage());
        static::assertSame(['countryId' => 'id-1'], $exception->getParameters());
    }

    public function testUnsupportedOperator(): void
    {
        $exception = UserException::unsupportedOperator('$', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::OPERATOR_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $exception->getMessage());
        static::assertSame(['operator' => '$', 'class' => 'testClass'], $exception->getParameters());
    }

    public function testUnsupportedValue(): void
    {
        $exception = UserException::unsupportedValue('badType', 'testClass');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(UserException::VALUE_NOT_SUPPORTED, $exception->getErrorCode());
        static::assertSame('Unsupported value of type badType in testClass', $exception->getMessage());
        static::assertSame(['type' => 'badType', 'class' => 'testClass'], $exception->getParameters());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedOperatorDeprecated(): void
    {
        $exception = UserException::unsupportedOperator('$', 'testClass');

        static::assertInstanceOf(UnsupportedOperatorException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CONTENT__RULE_OPERATOR_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertSame('Unsupported operator $ in testClass', $exception->getMessage());
        static::assertSame(['operator' => '$', 'class' => 'testClass'], $exception->getParameters());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedValueDeprecated(): void
    {
        $exception = UserException::unsupportedValue('badType', 'testClass');

        static::assertInstanceOf(UnsupportedValueException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CONTENT__RULE_VALUE_NOT_SUPPORTED', $exception->getErrorCode());
        static::assertSame('Unsupported value of type badType in testClass', $exception->getMessage());
        static::assertSame(['type' => 'badType', 'class' => 'testClass'], $exception->getParameters());
    }
}
