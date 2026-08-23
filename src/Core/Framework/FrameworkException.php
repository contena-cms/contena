<?php declare(strict_types=1);

namespace Contena\Core\Framework;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FrameworkException extends HttpException
{
    private const string PROJECT_DIR_NOT_EXISTS = 'FRAMEWORK__PROJECT_DIR_NOT_EXISTS';

    private const string INVALID_KERNEL_CACHE_DIR = 'FRAMEWORK__INVALID_KERNEL_CACHE_DIR';

    private const string INVALID_EVENT_DATA = 'FRAMEWORK__INVALID_EVENT_DATA';

    private const string INVALID_ARGUMENT = 'FRAMEWORK__INVALID_ARGUMENT';

    private const string INVALID_COLLECTION_ELEMENT_TYPE = 'FRAMEWORK__INVALID_COLLECTION_ELEMENT_TYPE';

    private const string INVALID_COMPRESSION_METHOD = 'FRAMEWORK__INVALID_COMPRESSION_METHOD';
    private const string VALIDATION_FAILED = 'FRAMEWORK__VALIDATION_FAILED';
    private const string CLASS_NOT_FOUND = 'FRAMEWORK__CLASS_NOT_FOUND';
    private const string CONTEXT_RULES_LOCKED = 'FRAMEWORK__CONTEXT_RULES_LOCKED';

    private const string MISSING_OPTIONS = 'FRAMEWORK__MISSING_OPTIONS';
    private const string INVALID_OPTIONS = 'FRAMEWORK__INVALID_OPTIONS';
    private const string ASSOCIATION_NOT_FOUND = 'FRAMEWORK__ASSOCIATION_NOT_FOUND';

    public static function projectDirNotExists(string $dir, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_NOT_EXISTS,
            'Project directory "{{ dir }}" does not exist.',
            ['dir' => $dir],
            $e
        );
    }

    public static function invalidKernelCacheDir(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_KERNEL_CACHE_DIR,
            'Container parameter "kernel.cache_dir" needs to be a string.'
        );
    }

    public static function invalidEventData(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_EVENT_DATA,
            $message
        );
    }

    public static function invalidCompressionMethod(string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COMPRESSION_METHOD,
            \sprintf('Invalid cache compression method: %s', $method),
        );
    }

    public static function invalidArgumentException(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ARGUMENT,
            $message
        );
    }

    public static function validationFailed(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::VALIDATION_FAILED,
            $message
        );
    }

    public static function classNotFound(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CLASS_NOT_FOUND,
            'Class not found: ' . $class
        );
    }

    public static function collectionElementInvalidType(string $expectedClass, string $elementClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COLLECTION_ELEMENT_TYPE,
            'Expected collection element of type {{ expected }} got {{ element }}',
            ['expected' => $expectedClass, 'element' => $elementClass]
        );
    }

    public static function contextRulesLocked(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONTEXT_RULES_LOCKED,
            'Context rules in application context already locked.'
        );
    }

    public static function missingOptions(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_OPTIONS,
            $message
        );
    }

    public static function invalidOptions(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_OPTIONS,
            $message
        );
    }

    public static function associationNotFound(string $association): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ASSOCIATION_NOT_FOUND,
            'Can not find association by name {{ association }}',
            ['association' => $association]
        );
    }

    public static function unexpectedType(mixed $givenType, string $expectedType): UnexpectedTypeException
    {
        return new UnexpectedTypeException($givenType, $expectedType);
    }
}
