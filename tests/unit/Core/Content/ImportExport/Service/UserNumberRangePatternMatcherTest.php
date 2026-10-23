<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\Service\UserNumberRangePatternMatcher;

/**
 * @internal
 */
#[CoversClass(UserNumberRangePatternMatcher::class)]
class UserNumberRangePatternMatcherTest extends TestCase
{
    #[DataProvider('userNumberPatterns')]
    public function testExtractsIncrementFromUserNumberPattern(
        string $pattern,
        string $userNumber,
        int $expectedIncrement,
    ): void {
        $matcher = new UserNumberRangePatternMatcher();

        static::assertSame($expectedIncrement, $matcher->extractIncrement($pattern, $userNumber));
    }

    /**
     * @return iterable<string, array{pattern: string, userNumber: string, expectedIncrement: int}>
     */
    public static function userNumberPatterns(): iterable
    {
        yield 'plain increment' => [
            'pattern' => '{n}',
            'userNumber' => '100014',
            'expectedIncrement' => 100014,
        ];

        yield 'prefix and suffix' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'userNumber' => 'CUSTOMER-100014-EU',
            'expectedIncrement' => 100014,
        ];

        yield 'increment before default date' => [
            'pattern' => '{n}-{date}',
            'userNumber' => '100014-2026-08-12',
            'expectedIncrement' => 100014,
        ];

        yield 'default date before increment' => [
            'pattern' => '{date}_{n}',
            'userNumber' => '2026-08-12_100014',
            'expectedIncrement' => 100014,
        ];

        yield 'custom date format' => [
            'pattern' => 'DOC-{date_d.m.Y}/{n}',
            'userNumber' => 'DOC-12.08.2026/100014',
            'expectedIncrement' => 100014,
        ];

        yield 'whitespace and special characters' => [
            'pattern' => 'User / {n} (EU) #1',
            'userNumber' => 'User / 100014 (EU) #1',
            'expectedIncrement' => 100014,
        ];

        yield 'increment directly before date' => [
            'pattern' => 'PREFIX-{n}{date_Ymd}',
            'userNumber' => 'PREFIX-10001420260812',
            'expectedIncrement' => 100014,
        ];

        yield 'date directly before increment' => [
            'pattern' => 'PREFIX-{date_Ymd}{n}',
            'userNumber' => 'PREFIX-20260812100014',
            'expectedIncrement' => 100014,
        ];

        yield 'ambiguous literal and placeholder adjacency' => [
            'pattern' => 'PREFIX-99{n}12{date_Ymd}42',
            'userNumber' => 'PREFIX-99100014122026081242',
            'expectedIncrement' => 100014,
        ];
    }

    #[DataProvider('invalidUserNumberPatterns')]
    public function testReturnsNullForInvalidUserNumberPattern(
        string $pattern,
        string $userNumber,
    ): void {
        $matcher = new UserNumberRangePatternMatcher();

        static::assertNull($matcher->extractIncrement($pattern, $userNumber));
    }

    /**
     * @return iterable<string, array{pattern: string, userNumber: string}>
     */
    public static function invalidUserNumberPatterns(): iterable
    {
        yield 'literal suffix does not match' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'userNumber' => 'CUSTOMER-100014-DE',
        ];

        yield 'missing increment value' => [
            'pattern' => 'CUSTOMER-{n}',
            'userNumber' => 'CUSTOMER-ABC',
        ];

        yield 'invalid date' => [
            'pattern' => '{date}_{n}',
            'userNumber' => '2026-99-99_100014',
        ];

        yield 'missing increment placeholder' => [
            'pattern' => 'CUSTOMER-{date}',
            'userNumber' => 'CUSTOMER-2026-08-12',
        ];

        yield 'unknown placeholder' => [
            'pattern' => 'CUSTOMER-{external-value}-{n}',
            'userNumber' => 'CUSTOMER-value-100014',
        ];
    }
}
