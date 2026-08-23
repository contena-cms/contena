<?php declare(strict_types=1);

namespace Contena\Core\Framework\Feature;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class FeatureException extends HttpException
{
    final public const string FEATURE_NOT_REGISTERED = 'FRAMEWORK__FEATURE_NOT_REGISTERED';
    final public const string FEATURE_CANNOT_BE_TOGGLE = 'FRAMEWORK__FEATURE_CANNOT_BE_TOGGLE';
    final public const string FEATURE_ERROR = 'FRAMEWORK__FEATURE_ERROR';

    public static function featureNotRegistered(string $feature): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURE_NOT_REGISTERED,
            'Feature "{{ feature }}" is not registered.',
            ['feature' => $feature]
        );
    }

    public static function featureCannotBeToggled(string $feature): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURE_CANNOT_BE_TOGGLE,
            'Feature "{{ feature }}" cannot be toggled.',
            ['feature' => $feature]
        );
    }

    public static function error(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FEATURE_ERROR,
            $message
        );
    }
}
