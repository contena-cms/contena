<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class SnippetException extends HttpException
{
    final public const string SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION = 'SNIPPET__EXTEND_OR_OVERWRITE_CORE';
    final public const string SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION = 'SNIPPET__DEFAULT_LANGUAGE_NOT_GIVEN';
    final public const string SNIPPET_INVALID_FILE_EXCEPTION = 'SNIPPET__INVALID_SNIPPET_FILE';

    /**
     * @param array<string> $keys
     */
    public static function extendOrOverwriteCore(array $keys): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION,
            'The following keys extend or overwrite the core snippets which is not allowed: {{ keys }}',
            ['keys' => implode(', ', $keys)]
        );
    }

    public static function defaultLanguageNotGiven(string $defaultLanguage): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION,
            'The following snippet file must always be provided when providing snippets: {{ defaultLanguage }}',
            ['defaultLanguage' => $defaultLanguage]
        );
    }

    public static function invalidSnippetFile(string $filePath, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SNIPPET_INVALID_FILE_EXCEPTION,
            'The administration snippet file "{{ filePath }}" is invalid: {{ message }}',
            ['filePath' => $filePath, 'message' => $previous->getMessage()],
            $previous
        );
    }
}
