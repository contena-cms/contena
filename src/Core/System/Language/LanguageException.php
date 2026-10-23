<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;

/**
 * @codeCoverageIgnore
 */
class LanguageException extends HttpException
{
    public const VALUE_NOT_SUPPORTED = 'LANGUAGE__RULE_VALUE_NOT_SUPPORTED';

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
}
