<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;

/**
 * @internal
 */
#[CoversClass(DatabaseChannelThemeLoader::class)]
class DatabaseChannelThemeLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private DatabaseChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->themeLoader = new DatabaseChannelThemeLoader(
            $this->connection,
        );
    }

    public function testLoadWithDifferentChannel(): void
    {
        $expectedDB = [
            'themeName' => 'Frontend',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];

        $expectedTheme = [
            'Frontend',
        ];

        $this->connection->expects($this->exactly(2))->method('fetchAssociative')->willReturnOnConsecutiveCalls($expectedDB, []);

        $channelId = Uuid::randomHex();

        $actualTheme = $this->themeLoader->load($channelId);
        static::assertSame($expectedTheme, $actualTheme);

        $otherChannelId = Uuid::randomHex();
        $secondAttempt = $this->themeLoader->load($otherChannelId);
        static::assertSame([], $secondAttempt);
    }

    public function testLoadMultiple(): void
    {
        $expectedDB1 = [
            'themeName' => 'Extended thrice',
            'parentThemeName' => 'Extended twice',
            'themeId' => Uuid::randomHex(),
            'grandParentThemeId' => Uuid::randomHex(),
        ];

        $expectedDB2 = [
            'themeName' => 'Extended once',
            'parentThemeName' => 'Extended',
            'grandParentThemeId' => Uuid::randomHex(),
        ];

        $expectedDB3 = [
            'themeName' => 'Frontend',
            'parentThemeName' => null,
            'grandParentThemeId' => null,
        ];

        $expectedTheme = [
            'Extended thrice',
            'Extended twice',
            'Extended once',
            'Extended',
            'Frontend',
        ];

        $this->connection->expects($this->exactly(4))->method('fetchAssociative')->willReturnOnConsecutiveCalls($expectedDB1, $expectedDB2, $expectedDB3, []);
        $channelId = Uuid::randomHex();

        $actualTheme = $this->themeLoader->load($channelId);
        static::assertSame($expectedTheme, $actualTheme);

        $otherChannelId = Uuid::randomHex();
        $secondAttempt = $this->themeLoader->load($otherChannelId);
        static::assertSame([], $secondAttempt);
    }

    public function testLoadWithMissingThemeNameUsesParentTheme(): void
    {
        $expectedDB = [
            'themeName' => null,
            'parentThemeName' => 'CtTheme',
            'themeId' => Uuid::randomHex(),
        ];

        $this->connection->expects($this->once())->method('fetchAssociative')->willReturn($expectedDB);

        $actualTheme = $this->themeLoader->load(Uuid::randomHex());
        static::assertSame(['CtTheme'], $actualTheme);
    }

    public function testGrandParentThemeWithMissingThemeNameIsReindexed(): void
    {
        // When the grandparent's themeName is null, array_filter leaves a gap at index 0.
        // array_values ensures the result is contiguous so assertSame (key-strict) passes.
        $channelTheme = [
            'themeName' => 'ChildTheme',
            'parentThemeName' => 'ParentTheme',
            'themeId' => Uuid::randomHex(),
            'grandParentThemeId' => Uuid::randomHex(),
        ];

        $grandParentTheme = [
            'themeName' => null,
            'parentThemeName' => 'GrandParentTheme',
            'grandParentThemeId' => null,
        ];

        $this->connection->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($channelTheme, $grandParentTheme);

        $actualTheme = $this->themeLoader->load(Uuid::randomHex());

        static::assertSame(['ChildTheme', 'ParentTheme', 'GrandParentTheme'], $actualTheme);
    }
}
