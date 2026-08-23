<?php declare(strict_types=1);

namespace Contena\Core\System\Locale;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class LocaleException extends HttpException
{
    final public const string LOCALE_DOES_NOT_EXISTS_EXCEPTION = 'SYSTEM__LOCALE_DOES_NOT_EXISTS';
    final public const string LANGUAGE_NOT_FOUND = 'SYSTEM__LANGUAGE_NOT_FOUND';

    public static function localeDoesNotExists(string $locale): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LOCALE_DOES_NOT_EXISTS_EXCEPTION,
            'The locale {{ locale }} does not exist.',
            ['locale' => $locale]
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
}
