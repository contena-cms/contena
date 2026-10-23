<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class AppXmlParsingException extends AppException
{
    public static function cannotParseFile(string $xmlFile, string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message],
        );
    }

    public static function cannotParseContent(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse XML content. Message: {{ message }}',
            ['message' => $message],
        );
    }
}
