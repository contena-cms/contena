<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ChannelException::class)]
class ChannelExceptionTest extends TestCase
{
    public function testMcpToolResultNotFoundMatchesTheAdminApiServer(): void
    {
        $e = ChannelException::mcpToolResultNotFound('abc');

        static::assertSame('Tool result "abc" not found. It may have expired or been stored by another caller.', $e->uri);
    }

    #[DataProvider('exceptionDataProvider')]
    public function testExceptions(ContenaHttpException|ChannelException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
        static::assertSame($message, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{exception: ContenaHttpException|ChannelException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield ChannelException::CRITERIA_TOO_MANY_NESTED_CRITERIA => [
            'exception' => ChannelException::tooManyNestedCriteria(100),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => ChannelException::CRITERIA_TOO_MANY_NESTED_CRITERIA,
            'message' => 'The criteria contains more than 100 nested criteria.',
        ];

        yield ChannelException::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION => [
            'exception' => ChannelException::providedLanguageNotAvailable('myCustomScn', ['scn1', 'scn2']),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION,
            'message' => 'Provided language "myCustomScn" is not in list of available languages: scn1, scn2',
        ];

        yield ChannelException::CHANNEL_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => ChannelException::channelNotFound('myCustomScn'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::CHANNEL_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Sales channel with id "myCustomScn" not found or not valid!.',
        ];

        yield ChannelException::LANGUAGE_INVALID_EXCEPTION => [
            'exception' => ChannelException::invalidLanguageId(),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::LANGUAGE_INVALID_EXCEPTION,
            'message' => 'Provided language ID is not a valid UUID',
        ];

        yield ChannelException::NO_CONTEXT_DATA_EXCEPTION => [
            'exception' => ChannelException::noContextData('myCustomScn'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::NO_CONTEXT_DATA_EXCEPTION,
            'message' => 'No context data found for Channel "myCustomScn"',
        ];

        yield ChannelException::COUNTRY_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => ChannelException::countryNotFound('myCustomCountry'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::COUNTRY_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Could not find country with id "myCustomCountry"',
        ];

        yield ChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => ChannelException::countryStateNotFound('myCustomCountryState'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::COUNTRY_STATE_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Could not find country state with id "myCustomCountryState"',
        ];

        yield ChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => ChannelException::currencyNotFound('myCustomCurrency'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => ChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'Could not find currency with id "myCustomCurrency"',
        ];

        yield ChannelException::LANGUAGE_NOT_FOUND => [
            'exception' => ChannelException::languageNotFound('myCustomLanguage'),
            'statusCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorCode' => ChannelException::LANGUAGE_NOT_FOUND,
            'message' => 'Could not find language with id "myCustomLanguage"',
        ];

        yield 'payment method not found exception' => [
            'exception' => ChannelException::unknownPaymentMethod('myCustomPaymentMethod'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CHECKOUT__UNKNOWN_PAYMENT_METHOD',
            'message' => 'Could not find payment method with id "myCustomPaymentMethod"',
        ];

        yield ChannelException::CONTEXT_TOKEN_NOT_ACCESSIBLE => [
            'exception' => ChannelException::contextTokenNotAccessible(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => ChannelException::CONTEXT_TOKEN_NOT_ACCESSIBLE,
            'message' => 'The context token is not accessible in Twig rendering context, as the token should never be leaked in HTML content.',
        ];
    }

    public function testWrongTypeInTheRequestIsAClientError(): void
    {
        $exception = ChannelException::invalidType('The includes must be of the type array, string given');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ChannelException::INVALID_TYPE, $exception->getErrorCode());
        static::assertSame('The includes must be of the type array, string given', $exception->getMessage());
    }
}
