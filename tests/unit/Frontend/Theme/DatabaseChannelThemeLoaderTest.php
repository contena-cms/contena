<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DatabaseChannelThemeLoader::class)]
class DatabaseChannelThemeLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private DatabaseChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->themeLoader = new DatabaseChannelThemeLoader($this->connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $expected
     */
    #[DataProvider('themeGraphProvider')]
    public function testLoad(array $rows, array $expected): void
    {
        $this->connection->expects($this->once())->method('fetchAllAssociative')->willReturn($rows);

        static::assertSame($expected, $this->themeLoader->load(Uuid::randomHex()));
    }

    public static function themeGraphProvider(): \Generator
    {
        yield 'no theme assigned to the sales channel' => [
            [self::row('storefront', 'Frontend')],
            [],
        ];

        yield 'only the base theme' => [
            [self::row('storefront', 'Frontend', assigned: true)],
            ['Frontend'],
        ];

        yield 'linear parent_theme_id chain' => [
            [
                self::row('t1', 'Extended thrice', parentThemeId: 't2', assigned: true),
                self::row('t2', 'Extended twice', parentThemeId: 't3'),
                self::row('t3', 'Extended once', parentThemeId: 't4'),
                self::row('t4', 'Extended', parentThemeId: 'storefront'),
                self::row('storefront', 'Frontend'),
            ],
            ['Extended thrice', 'Extended twice', 'Extended once', 'Extended', 'Frontend'],
        ];

        // addParentTheme() stored BasicTheme, ParentTheme is only reachable through configInheritance.
        yield 'multiple configInheritance parents with a stale parent_theme_id' => [
            [
                self::row('child', 'ChildTheme', parentThemeId: 'basic', assigned: true, configInheritance: ['@Frontend', '@BasicTheme', '@ParentTheme']),
                self::row('parent', 'ParentTheme', parentThemeId: 'basic', configInheritance: ['@Frontend', '@BasicTheme']),
                self::row('basic', 'BasicTheme', configInheritance: ['@Frontend']),
                self::row('storefront', 'Frontend'),
            ],
            ['ChildTheme', 'BasicTheme', 'ParentTheme', 'Frontend'],
        ];

        yield 'configInheritance is expanded transitively' => [
            [
                self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@ParentTheme']),
                self::row('parent', 'ParentTheme', configInheritance: ['@BasicTheme']),
                self::row('basic', 'BasicTheme'),
            ],
            ['ChildTheme', 'ParentTheme', 'BasicTheme'],
        ];

        yield 'database copy without technical name uses its parent' => [
            [
                self::row('copy', null, parentThemeId: 'child', assigned: true),
                self::row('child', 'ChildTheme', configInheritance: ['@Frontend', '@BasicTheme']),
                self::row('basic', 'BasicTheme'),
                self::row('storefront', 'Frontend'),
            ],
            ['ChildTheme', 'BasicTheme', 'Frontend'],
        ];

        yield 'cyclic inheritance terminates' => [
            [
                self::row('a', 'A', parentThemeId: 'b', assigned: true, configInheritance: ['@B']),
                self::row('b', 'B', parentThemeId: 'a', configInheritance: ['@A']),
            ],
            ['A', 'B'],
        ];

        yield 'configInheritance naming an uninstalled theme is ignored' => [
            [self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@NotInstalled'])],
            ['ChildTheme'],
        ];

        yield 'theme referencing itself is not duplicated' => [
            [self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@ChildTheme'])],
            ['ChildTheme'],
        ];

        yield 'missing base_config' => [
            [self::row('child', 'ChildTheme', parentThemeId: 'storefront', assigned: true), self::row('storefront', 'Frontend')],
            ['ChildTheme', 'Frontend'],
        ];

        yield 'malformed base_config' => [
            [
                ['themeId' => 'child', 'technicalName' => 'ChildTheme', 'parentThemeId' => null, 'configInheritance' => 'not json', 'assigned' => 1],
            ],
            ['ChildTheme'],
        ];
    }

    public function testResultIsMemoisedPerChannel(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([
            self::row('storefront', 'Frontend', assigned: true),
        ]);

        $channelId = Uuid::randomHex();
        static::assertSame(['Frontend'], $this->themeLoader->load($channelId));
        static::assertSame(['Frontend'], $this->themeLoader->load($channelId));

        static::assertSame(['Frontend'], $this->themeLoader->load(Uuid::randomHex()));
    }

    public function testEmptyResultIsNotMemoised(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([]);

        $channelId = Uuid::randomHex();
        static::assertSame([], $this->themeLoader->load($channelId));
        static::assertSame([], $this->themeLoader->load($channelId));
    }

    public function testResetClearsTheMemoisedResult(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([
            self::row('storefront', 'Frontend', assigned: true),
        ]);

        $channelId = Uuid::randomHex();
        static::assertSame(['Frontend'], $this->themeLoader->load($channelId));

        $this->themeLoader->reset();

        static::assertSame(['Frontend'], $this->themeLoader->load($channelId));
    }

    /**
     * @param list<string>|null $configInheritance
     *
     * @return array<string, mixed>
     */
    private static function row(
        string $themeId,
        ?string $technicalName,
        ?string $parentThemeId = null,
        bool $assigned = false,
        ?array $configInheritance = null,
    ): array {
        return [
            'themeId' => $themeId,
            'technicalName' => $technicalName,
            'parentThemeId' => $parentThemeId,
            'configInheritance' => $configInheritance === null ? null : json_encode($configInheritance, \JSON_THROW_ON_ERROR),
            'assigned' => $assigned ? 1 : 0,
        ];
    }
}
