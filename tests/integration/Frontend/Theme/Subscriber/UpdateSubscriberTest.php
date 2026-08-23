<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\Subscriber\UpdateSubscriber;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeService;

/**
 * @internal
 */
class UpdateSubscriberTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    protected function setUp(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `theme`');
    }

    public function testCompilesAllThemes(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $themeLifecycleService = static::getContainer()->get(ThemeLifecycleService::class);
        $channelRepository = static::getContainer()->get('channel.repository');

        $context = Context::createDefaultContext();

        $themes = $this->setupThemes($context);

        $updateSubscriber = new UpdateSubscriber($themeService, $themeLifecycleService, $channelRepository);
        $event = new UpdatePostFinishEvent(Context::createDefaultContext(), 'v6.2.0', 'v6.2.1');

        $themeService->expects($this->atLeast(2))
            ->method('compileThemeById')
            ->willReturnCallback(function ($themeId, $c) use (&$themes, $context) {
                $this->assertEquals($context, $c);
                $compiledThemes = [];
                if (isset($themes['otherTheme']) && $themes['otherTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['otherTheme']['id'];
                    unset($themes['otherTheme']);
                } elseif (isset($themes['parentTheme']) && $themes['parentTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['parentTheme']['id'];
                    unset($themes['parentTheme']);
                    if (isset($themes['childTheme'])) {
                        $compiledThemes[] = $themes['childTheme']['id'];
                        unset($themes['childTheme']);
                    }
                } elseif (isset($themes['childTheme']) && $themes['childTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['childTheme']['id'];
                    unset($themes['childTheme']);
                }

                unset($themes[$themeId]);

                return $compiledThemes;
            });

        $updateSubscriber->updateFinished($event);
        static::assertEmpty($themes, print_r($themes, true));
    }

    public function testThemesAreNotCompiledWithStateSkipAssetBuilding(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $themeLifecycleService = static::getContainer()->get(ThemeLifecycleService::class);

        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $context = Context::createDefaultContext();

        $this->setupThemes($context);

        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $updateSubscriber = new UpdateSubscriber($themeService, $themeLifecycleService, $channelRepository);
        $event = new UpdatePostFinishEvent($context, 'v6.2.0', 'v6.2.1');

        $themeService->expects($this->never())->method('compileThemeById');

        $updateSubscriber->updateFinished($event);
    }

    /**
     * @return array<string, array{id: string, channelId: string}>
     */
    private function setupThemes(Context $context): array
    {
        /** @var EntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = static::getContainer()->get('theme.repository');
        $themeChannelRepository = static::getContainer()->get('theme_channel.repository');

        $parentThemeId = Uuid::randomHex();
        $otherThemeId = Uuid::randomHex();
        $childThemeId = Uuid::randomHex();
        $themes = [
            'parentTheme' => [
                'id' => $parentThemeId,
                'channelId' => Uuid::randomHex(),
            ],
            'otherTheme' => [
                'id' => $otherThemeId,
                'channelId' => Uuid::randomHex(),
            ],
            'childTheme' => [
                'id' => $childThemeId,
                'channelId' => Uuid::randomHex(),
            ],
        ];

        $themeRepository->create(
            [
                [
                    'id' => $parentThemeId,
                    'name' => 'Parent theme',
                    'technicalName' => 'parentTheme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $childThemeId,
                    'parentThemeId' => $parentThemeId,
                    'name' => 'Child theme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $otherThemeId,
                    'name' => 'Other theme',
                    'technicalName' => 'otherTheme',
                    'author' => 'test',
                    'active' => true,
                ],
            ],
            $context
        );

        foreach ($themes as $theme) {
            $this->createChannel([
                'id' => $theme['channelId'], 'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/' . $theme['id'],
                    ],
                ],
            ]);

            $themeChannelRepository->create(
                [
                    ['themeId' => $theme['id'], 'channelId' => $theme['channelId']],
                ],
                $context
            );
        }

        return $themes;
    }
}
