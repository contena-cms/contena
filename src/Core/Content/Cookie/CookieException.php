<?php

declare(strict_types=1);

namespace Contena\Core\Content\Cookie;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class CookieException extends HttpException
{
    final public const string NOT_ALLOWED_PROPERTY_ASSIGNMENT = 'CONTENT__COOKIE_NOT_ALLOWED_PROPERTY_ASSIGNMENT';
    final public const string HASH_GENERATION_FAILED = 'CONTENT__COOKIE_HASH_GENERATION_FAILED';
    final public const string INVALID_CONSENT_LOG_PAYLOAD = 'CONTENT__COOKIE_INVALID_CONSENT_LOG_PAYLOAD';

    public static function notAllowedPropertyAssignment(string $propertyToBeAssigned, string $alreadyAssignedProperty): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::NOT_ALLOWED_PROPERTY_ASSIGNMENT,
            'Property "{{ propertyToBeAssigned }}" cannot be set, as "{{ alreadyAssignedProperty }}" is already set.',
            ['propertyToBeAssigned' => $propertyToBeAssigned, 'alreadyAssignedProperty' => $alreadyAssignedProperty],
        );
    }

    public static function hashGenerationFailed(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::HASH_GENERATION_FAILED,
            'Failed to generate cookie configuration hash: {{ reason }}',
            ['reason' => $reason],
        );
    }

    public static function invalidConsentLogPayload(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CONSENT_LOG_PAYLOAD,
            'Invalid cookie consent log payload: {{ reason }}',
            ['reason' => $reason],
        );
    }
}
