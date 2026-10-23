<?php
declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Frontend\Theme\ConfigLoader\DatabaseAvailableThemeProvider;

/**
 * @internal
 */
class DatabaseAvailableThemeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testReadChannels(): void
    {
        $themeId = $this->getThemeId();

        $firstSc = $this->createChannel();
        $secondSc = $this->createChannel([
            'active' => false,
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        $list = static::getContainer()->get(DatabaseAvailableThemeProvider::class)->load(Context::createDefaultContext(), false);

        static::assertArrayNotHasKey($firstSc['id'], $list, 'sc has no theme assigned');
        static::assertArrayHasKey($secondSc['id'], $list, 'sc has no theme assigned');
        static::assertSame($themeId, $list[$secondSc['id']]);
    }

    public function testItFiltersInactiveChannels(): void
    {
        $themeId = $this->getThemeId();

        $inactive = $this->createChannel([
            'active' => false,
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        $list = static::getContainer()->get(DatabaseAvailableThemeProvider::class)->load(Context::createDefaultContext(), true);

        static::assertArrayNotHasKey($inactive['id'], $list, 'inactive sales channel was returned but shouldn\'t');
    }

    private function getThemeId(): string
    {
        $id = static::getContainer()->get('theme.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        static::assertIsString($id);

        return $id;
    }
}
