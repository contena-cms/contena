<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;

/**
 * @internal
 */
class DatabaseChannelThemeLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private DatabaseChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->themeLoader = static::getContainer()->get(DatabaseChannelThemeLoader::class);
        $this->themeLoader->reset();
    }

    public function testUsedThemesContainEveryConfigInheritanceParent(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Frontend']);
        $this->createTheme('ParentTheme', ['@Frontend', '@BasicTheme'], $basicId);

        // addParentTheme() stored BasicTheme, ParentTheme is only reachable through configInheritance.
        $childId = $this->createTheme('ChildTheme', ['@Frontend', '@BasicTheme', '@ParentTheme'], $basicId);

        $this->assignThemeToChannel($childId, TestDefaults::CHANNEL);

        static::assertSame(
            ['ChildTheme', 'BasicTheme', 'ParentTheme', 'Frontend'],
            $this->themeLoader->load(TestDefaults::CHANNEL)
        );
    }

    public function testConfigInheritanceIsExpandedTransitively(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Frontend']);
        $this->createTheme('ParentTheme', ['@Frontend', '@BasicTheme'], $basicId);
        $childId = $this->createTheme('ChildTheme', ['@ParentTheme']);

        $this->assignThemeToChannel($childId, TestDefaults::CHANNEL);

        static::assertSame(
            ['ChildTheme', 'ParentTheme', 'BasicTheme', 'Frontend'],
            $this->themeLoader->load(TestDefaults::CHANNEL)
        );
    }

    public function testThemeCopyWithoutTechnicalNameResolvesThroughItsParent(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Frontend']);
        $childId = $this->createTheme('ChildTheme', ['@Frontend', '@BasicTheme'], $basicId);
        $copyId = $this->createTheme(null, null, $childId);

        $this->assignThemeToChannel($copyId, TestDefaults::CHANNEL);

        $usedThemes = $this->themeLoader->load(TestDefaults::CHANNEL);

        static::assertSame('ChildTheme', $usedThemes[0]);
        static::assertSame(['ChildTheme', 'BasicTheme', 'Frontend'], $usedThemes);
    }

    /**
     * @param list<string>|null $configInheritance
     */
    private function createTheme(?string $technicalName, ?array $configInheritance = null, ?string $parentThemeId = null): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('theme', [
            'id' => Uuid::fromHexToBytes($id),
            'technical_name' => $technicalName,
            'name' => $technicalName ?? 'Theme copy',
            'author' => 'test',
            'active' => 1,
            'base_config' => $configInheritance === null
                ? null
                : json_encode(['configInheritance' => $configInheritance], \JSON_THROW_ON_ERROR),
            'parent_theme_id' => $parentThemeId !== null ? Uuid::fromHexToBytes($parentThemeId) : null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    private function assignThemeToChannel(string $themeId, string $channelId): void
    {
        $this->connection->executeStatement(
            'REPLACE INTO theme_channel (theme_id, channel_id) VALUES (:themeId, :channelId)',
            [
                'themeId' => Uuid::fromHexToBytes($themeId),
                'channelId' => Uuid::fromHexToBytes($channelId),
            ]
        );
    }
}
