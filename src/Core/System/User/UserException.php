<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraint;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\System\User\Exception\AddressNotFoundException;
use Contena\Core\System\User\Exception\CannotDeleteDefaultAddressException;
use Contena\Core\System\User\Exception\DuplicateWishlistBlogException;
use Contena\Core\System\User\Exception\InvalidImitateUserTokenException;
use Contena\Core\System\User\Exception\PasswordPoliciesUpdatedException;
use Contena\Core\System\User\Exception\UserAlreadyConfirmedException;
use Contena\Core\System\User\Exception\UserAuthThrottledException;
use Contena\Core\System\User\Exception\UserNotFoundByHashException;
use Contena\Core\System\User\Exception\UserNotFoundByIdException;
use Contena\Core\System\User\Exception\UserOptinNotCompletedException;
use Contena\Core\System\User\Exception\UserRecoveryHashExpiredException;
use Contena\Core\System\User\Exception\UserWishlistNotFoundException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UserException extends HttpException
{
    public const CUSTOMERS_NOT_FOUND = 'CHECKOUT__CUSTOMERS_NOT_FOUND';
    public const CUSTOMER_NOT_FOUND = 'CHECKOUT__CUSTOMER_NOT_FOUND';
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';
    public const CUSTOMER_GROUP_REQUEST_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_REQUEST_NOT_FOUND';
    public const CUSTOMER_NOT_LOGGED_IN = 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN';
    public const LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND = 'CHECKOUT__LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND';
    public const CUSTOMER_IDS_PARAMETER_IS_MISSING = 'CHECKOUT__CUSTOMER_IDS_PARAMETER_IS_MISSING';
    public const BLOG_IDS_PARAMETER_IS_MISSING = 'CHECKOUT__BLOG_IDS_PARAMETER_IS_MISSING';
    public const CUSTOMER_ADDRESS_NOT_FOUND = 'CHECKOUT__CUSTOMER_ADDRESS_NOT_FOUND';
    public const CUSTOMER_AUTH_BAD_CREDENTIALS = 'CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS';
    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public const CUSTOMER_ADDRESS_IS_ACTIVE = 'CHECKOUT__CUSTOMER_ADDRESS_IS_ACTIVE';
    public const CUSTOMER_ADDRESS_IS_DEFAULT = 'CHECKOUT__CUSTOMER_ADDRESS_IS_DEFAULT';
    public const CUSTOMER_IS_ALREADY_CONFIRMED = 'CHECKOUT__CUSTOMER_IS_ALREADY_CONFIRMED';
    public const CUSTOMER_GROUP_REGISTRATION_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_REGISTRATION_NOT_FOUND';
    public const CUSTOMER_NOT_FOUND_BY_HASH = 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_HASH';
    public const CUSTOMER_NOT_FOUND_BY_ID = 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_ID';
    public const CUSTOMER_RECOVERY_HASH_EXPIRED = 'CHECKOUT__CUSTOMER_RECOVERY_HASH_EXPIRED';
    public const WISHLIST_IS_NOT_ACTIVATED = 'CHECKOUT__WISHLIST_IS_NOT_ACTIVATED';
    public const WISHLIST_NOT_FOUND = 'CHECKOUT__WISHLIST_NOT_FOUND';
    public const COUNTRY_NOT_FOUND = 'CHECKOUT__CUSTOMER_COUNTRY_NOT_FOUND';
    public const DUPLICATE_WISHLIST_BLOG = 'CHECKOUT__DUPLICATE_WISHLIST_BLOG';
    public const LEGACY_PASSWORD_ENCODER_NOT_FOUND = 'CHECKOUT__LEGACY_PASSWORD_ENCODER_NOT_FOUND';
    public const NO_HASH_PROVIDED = 'CHECKOUT__NO_HASH_PROVIDED';
    public const WISHLIST_BLOG_NOT_FOUND = 'CHECKOUT__WISHLIST_BLOG_NOT_FOUND';
    public const CUSTOMER_AUTH_THROTTLED = 'CHECKOUT__CUSTOMER_AUTH_THROTTLED';
    public const CUSTOMER_OPTIN_NOT_COMPLETED = 'CHECKOUT__CUSTOMER_OPTIN_NOT_COMPLETED';
    public const CUSTOMER_CHANGE_PAYMENT_ERROR = 'CHECKOUT__CUSTOMER_CHANGE_PAYMENT_METHOD_NOT_FOUND';
    public const CUSTOMER_GUEST_AUTH_INVALID = 'CHECKOUT__CUSTOMER_AUTH_INVALID';
    public const IMITATE_CUSTOMER_INVALID_TOKEN = 'CHECKOUT__IMITATE_CUSTOMER_INVALID_TOKEN';
    public const MISSING_ROUTE_ANNOTATION = 'CHECKOUT__MISSING_ROUTE_ANNOTATION';
    public const MISSING_ROUTE_CHANNEL = 'CHECKOUT__MISSING_ROUTE_CHANNEL';
    public const OPERATOR_NOT_SUPPORTED = 'CHECKOUT__CUSTOMER_RULE_OPERATOR_NOT_SUPPORTED';
    public const VALUE_NOT_SUPPORTED = 'CONTENT__RULE_VALUE_NOT_SUPPORTED';
    public const MISSING_REQUEST_PARAMETER_CODE = 'CONTENT__MISSING_REQUEST_PARAMETER_CODE';
    /**
     * @deprecated tag:v6.8.0 - Use MISSING_OPTION instead
     */
    public const MISSING_OPTIONS = 'CONTENT__MISSING_OPTIONS';
    public const UNEXPECTED_TYPE = 'CHECKOUT__UNEXPECTED_TYPE';
    public const MISSING_OPTION = 'CONTENT__MISSING_OPTION';
    public const INVALID_OPTION = 'CONTENT__INVALID_OPTION';
    public const REGISTERED_CUSTOMER_CANNOT_BE_CONVERTED = 'CHECKOUT__REGISTERED_CUSTOMER_CANNOT_BE_CONVERTED';
    public const CUSTOMER_INACTIVE = 'CHECKOUT__CUSTOMER_INACTIVE';
    public const CHANNEL_DOMAIN_NOT_FOUND = 'CHECKOUT__CHANNEL_DOMAIN_NOT_FOUND';

    public static function userGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'user group', 'field' => 'id', 'value' => $id]
        );
    }

    public static function groupRequestNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_REQUEST_NOT_FOUND,
            'Group request for user "{{ id }}" is not found',
            ['id' => $id]
        );
    }

    /**
     * @param string[] $ids
     */
    public static function usersNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMERS_NOT_FOUND,
            'These users "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)]
        );
    }

    public static function userNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN,
            'User is not logged in.',
        );
    }

    public static function downloadFileNotFound(string $downloadId): ContenaHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND,
            'Line item download file with id "{{ downloadId }}" not found.',
            ['downloadId' => $downloadId]
        );
    }

    public static function userIdsParameterIsMissing(): ContenaHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_IDS_PARAMETER_IS_MISSING,
            'Parameter "userIds" is missing.',
        );
    }

    public static function unknownPaymentMethod(string $paymentMethodId): ContenaHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_CHANGE_PAYMENT_ERROR,
            'Change Payment to method {{ paymentMethodId }} not possible.',
            ['paymentMethodId' => $paymentMethodId]
        );
    }

    public static function blogIdsParameterIsMissing(): ContenaHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::BLOG_IDS_PARAMETER_IS_MISSING,
            'Parameter "blogIds" is missing.',
        );
    }

    public static function addressNotFound(string $id): AddressNotFoundException
    {
        return new AddressNotFoundException($id);
    }

    public static function countryNotFound(string $countryId): HttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            'Country with id "{{ countryId }}" not found.',
            ['countryId' => $countryId]
        );
    }


    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public static function cannotDeleteActiveAddress(string $id): ContenaHttpException
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0'));

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_IS_ACTIVE,
            'User address with id "{{ addressId }}" is an active address and cannot be deleted.',
            ['addressId' => $id]
        );
    }

    public static function cannotDeleteDefaultAddress(string $id): CannotDeleteDefaultAddressException
    {
        return new CannotDeleteDefaultAddressException($id);
    }

    public static function userAlreadyConfirmed(string $id): UserAlreadyConfirmedException
    {
        return new UserAlreadyConfirmedException($id);
    }

    public static function userGroupRegistrationConfigurationNotFound(string $userGroupId): ContenaHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_GROUP_REGISTRATION_NOT_FOUND,
            'User group registration for id {{ userGroupId }} not found.',
            ['userGroupId' => $userGroupId]
        );
    }

    public static function userNotFoundByHash(string $hash): UserNotFoundByHashException
    {
        return new UserNotFoundByHashException($hash);
    }

    public static function userNotFoundByIdException(string $id): UserNotFoundByIdException
    {
        return new UserNotFoundByIdException($id);
    }


    public static function userRecoveryHashExpired(string $hash): UserRecoveryHashExpiredException
    {
        return new UserRecoveryHashExpiredException($hash);
    }

    public static function userWishlistNotActivated(): ContenaHttpException
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::WISHLIST_IS_NOT_ACTIVATED,
            'Wishlist is not activated!'
        );
    }

    public static function userWishlistNotFound(): UserWishlistNotFoundException
    {
        return new UserWishlistNotFoundException();
    }

    public static function duplicateWishlistBlog(): DuplicateWishlistBlogException
    {
        return new DuplicateWishlistBlogException();
    }

    public static function legacyPasswordEncoderNotFound(string $encoder): ContenaHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LEGACY_PASSWORD_ENCODER_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'encoder', 'field' => 'name', 'value' => $encoder]
        );
    }

    public static function noHashProvided(): ContenaHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NO_HASH_PROVIDED,
            'The given hash is empty.'
        );
    }

    public static function wishlistBlogNotFound(string $blogId): ContenaHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_BLOG_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'wishlist blog', 'field' => 'id', 'value' => $blogId]
        );
    }

    public static function userOptinNotCompleted(string $id): UserOptinNotCompletedException
    {
        return new UserOptinNotCompletedException($id);
    }

    public static function userAuthThrottledException(int $waitTime, ?\Throwable $e = null): UserAuthThrottledException
    {
        return new UserAuthThrottledException(
            $waitTime,
            $e
        );
    }

    public static function guestAccountInvalidAuth(): ContenaHttpException
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_GUEST_AUTH_INVALID,
            'Guest account is not allowed to login'
        );
    }

    public static function passwordPoliciesUpdated(): PasswordPoliciesUpdatedException
    {
        return new PasswordPoliciesUpdatedException();
    }

    public static function invalidImitationToken(string $token): InvalidImitateUserTokenException
    {
        return new InvalidImitateUserTokenException($token);
    }

    public static function missingRouteAnnotation(string $annotation, string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ROUTE_ANNOTATION,
            'Missing @{{ annotation }} annotation for route: {{ route }}',
            ['annotation' => $annotation, 'route' => $route]
        );
    }

    public static function missingRouteChannel(string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ROUTE_CHANNEL,
            'Missing sales channel context for route {{ route }}',
            ['route' => $route]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function unsupportedOperator(string $operator, string $class): self|UnsupportedOperatorException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnsupportedOperatorException($operator, $class);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function unsupportedValue(string $type, string $class): self|UnsupportedValueException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnsupportedValueException($type, $class);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }

    public static function missingRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function blogNotFound(string $blogId): self|BlogNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new BlogNotFoundException($blogId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Blog for id {{ blogId }} not found.',
            ['blogId' => $blogId]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function missingOption(string $option, string $constraint): self|MissingOptionsException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new MissingOptionsException(\sprintf('Option "%s" must be given for constraint %s', $option, $constraint), ['context']);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_OPTION,
            'Option "{{ option }}" must be given for constraint {{ constraint }}',
            ['option' => $option, 'constraint' => $constraint]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function unexpectedType(Constraint $constraint, string $class): self|UnexpectedTypeException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnexpectedTypeException($constraint, UserEmailUnique::class);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNEXPECTED_TYPE,
            'Expected argument of type "{{ expectedType }}", "{{ givenType }}" given',
            ['expectedType' => $class, 'givenType' => get_debug_type($constraint)]
        );
    }

    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'self')]
    public static function invalidOption(string $option, string $type, string $constraint): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new \InvalidArgumentException(\sprintf('Option "%s" must be of type "%s" for constraint %s', $option, $type, $constraint));
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_OPTION,
            'Option "{{ option }}" must be of type "{{ type }}" for constraint {{ constraint }}',
            ['option' => $option, 'type' => $type, 'constraint' => $constraint]
        );
    }

    public static function registeredUserCannotBeConverted(string $userId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REGISTERED_CUSTOMER_CANNOT_BE_CONVERTED,
            'User with id "{{ userId }}" is not a guest',
            ['userId' => $userId],
        );
    }



    public static function unexpectedConstraintType(Constraint $constraint, string $expectedType): ValidatorException
    {
        return new UnexpectedTypeException($constraint, $expectedType);
    }

    public static function unexpectedConstraintValue(mixed $value, string $expectedType): ValidatorException
    {
        return new UnexpectedValueException($value, $expectedType);
    }

    public static function inactive(string $userId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_INACTIVE,
            'User with id "{{ userId }}" is inactive',
            ['userId' => $userId],
        );
    }

    public static function channelDomainNotFound(string $channelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHANNEL_DOMAIN_NOT_FOUND,
            'No domain for sales channel with id "{{ channelId }}" found',
            ['channelId' => $channelId],
        );
    }
}
