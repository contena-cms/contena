<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\VersionParser;
use Contena\Core\Kernel;

/**
 * @internal
 */
#[CoversClass(VersionParser::class)]
class VersionParserTest extends TestCase
{
    #[DataProvider('provideVersions')]
    public function testParseContenaVersion(string $unparsedVersion, string $parsedVersion, string $parsedRevision): void
    {
        $version = VersionParser::parseContenaVersion($unparsedVersion);

        static::assertSame($parsedVersion, $version['version']);
        static::assertSame($parsedRevision, $version['revision']);
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function provideVersions(): array
    {
        return [
            [
                '6.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.1.1.12-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.10.10.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.10.10.x-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.3.1.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.x-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                'v6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '12.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                Kernel::CONTENA_FALLBACK_VERSION,
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                'v6.3.1.1',
                Kernel::CONTENA_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                '6.2.1',
                Kernel::CONTENA_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                'foobar',
                Kernel::CONTENA_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                '1010806',
                Kernel::CONTENA_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
        ];
    }
}
