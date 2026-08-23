<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\HttpException;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ChannelException::class)]
class ChannelExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
    public function testExceptions(HttpException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
        static::assertSame($message, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{exception: HttpException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield 'channel not found' => [
            'exception' => ChannelException::channelNotFound('myChannel'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::CHANNEL_DOES_NOT_EXISTS,
            'message' => 'Channel with id "myChannel" not found or not valid!.',
        ];
        yield 'member group not found' => [
            'exception' => ChannelException::memberGroupNotFound('myMemberGroup'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::MEMBER_GROUP_DOES_NOT_EXISTS,
            'message' => 'Could not find member group with id "myMemberGroup"',
        ];
        yield 'language not found' => [
            'exception' => ChannelException::languageNotFound('myLanguage'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::LANGUAGE_NOT_FOUND,
            'message' => 'Could not find language with id "myLanguage"',
        ];
        yield 'no context data' => [
            'exception' => ChannelException::noContextData('myChannel'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::NO_CONTEXT_DATA_EXCEPTION,
            'message' => 'No context data found for Channel "myChannel"',
        ];
        yield 'member not logged in' => [
            'exception' => ChannelException::memberNotLoggedIn(),
            'statusCode' => Response::HTTP_UNAUTHORIZED,
            'errorCode' => ChannelException::MEMBER_NOT_LOGGED_IN,
            'message' => 'A logged-in member is required for this operation.',
        ];
        yield 'context token not accessible' => [
            'exception' => ChannelException::contextTokenNotAccessible(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => ChannelException::CONTEXT_TOKEN_NOT_ACCESSIBLE,
            'message' => 'The context token is not accessible in Twig rendering context, as the token should never be leaked in HTML content.',
        ];
        yield 'invalid channel file path' => [
            'exception' => ChannelException::invalidChannelFilePath('bad/path'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => ChannelException::CHANNEL_FILE_INVALID_PATH,
            'message' => 'The channel file path "bad/path" is invalid.',
        ];
    }
}
