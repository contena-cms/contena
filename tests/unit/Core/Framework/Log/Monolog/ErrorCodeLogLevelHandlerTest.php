<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\Handler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * @internal
 */
#[CoversClass(ErrorCodeLogLevelHandler::class)]
class ErrorCodeLogLevelHandlerTest extends TestCase
{
    /**
     * @param array<string, value-of<Level::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'> $errorCodeLogLevelMapping
     */
    #[DataProvider('cases')]
    public function testHandler(LogRecord $record, array $errorCodeLogLevelMapping, Level $expectedLogLevel): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function (LogRecord $record) use ($expectedLogLevel): bool {
                static::assertSame($expectedLogLevel, $record->level);

                return true;
            });

        $handler = new ErrorCodeLogLevelHandler(
            $innerHandler,
            $errorCodeLogLevelMapping
        );

        $handler->handle($record);
    }

    public function testResetIsForwardedToResettableInnerHandler(): void
    {
        $innerHandler = $this->createMock(FingersCrossedHandler::class);
        $innerHandler->expects($this->once())->method('reset');

        $handler = new ErrorCodeLogLevelHandler($innerHandler, []);

        $handler->reset();
    }

    public function testResetSkipsInnerHandlerWithoutReset(): void
    {
        // a handler without reset(): forwarding the call unconditionally would fail here
        $innerHandler = new class extends Handler {
            /**
             * @var list<LogRecord>
             */
            public array $records = [];

            public function isHandling(LogRecord $record): bool
            {
                return true;
            }

            public function handle(LogRecord $record): bool
            {
                $this->records[] = $record;

                return false;
            }
        };
        $handler = new ErrorCodeLogLevelHandler($innerHandler, []);

        $handler->reset();
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'after reset'));

        static::assertCount(1, $innerHandler->records);
        static::assertSame('after reset', $innerHandler->records[0]->message);
    }

    /**
     * @return iterable<string, array{0: LogRecord, 1: array<string, value-of<Level::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'>, 2: Level}>
     */
    public static function cases(): iterable
    {
        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message');
        yield 'log level stays same without exception' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message', ['exception' => new \RuntimeException('')]);
        yield 'log level stays same without contena exception' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        $logRecord = new LogRecord(new \DateTimeImmutable(), 'foo', Level::Alert, 'some message', ['exception' => BlogException::categoryNotFound(Uuid::randomHex())]);
        yield 'log level stays same without error code mapping' => [
            $logRecord,
            [],
            Level::Alert,
        ];

        yield 'log level stays same without matching error code mapping' => [
            $logRecord,
            [
                BlogException::BLOG_INVALID_CHEAPEST_PRICE_FACADE => 'notice',
            ],
            Level::Alert,
        ];

        yield 'log level is manipulated when error code mapping matches' => [
            $logRecord,
            [
                BlogException::CATEGORY_NOT_FOUND => 'notice',
            ],
            Level::Notice,
        ];

        $logRecord = new LogRecord(
            new \DateTimeImmutable(),
            'foo',
            Level::Alert,
            'some message',
            ['exception' => new HandlerFailedException(
                new Envelope(new \stdClass()),
                [BlogException::categoryNotFound(Uuid::randomHex())]
            )]
        );
        yield 'log level is manipulated based on inner exception for HandlerFailedException' => [
            $logRecord,
            [
                BlogException::CATEGORY_NOT_FOUND => 'notice',
            ],
            Level::Notice,
        ];

        yield 'log level is not manipulated when inner HandlerFailedException does not match' => [
            $logRecord,
            [
                BlogException::BLOG_INVALID_CHEAPEST_PRICE_FACADE => 'notice',
            ],
            Level::Alert,
        ];
    }
}
