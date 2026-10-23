<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\LandingPage\LandingPageLoadedHook;

/**
 * @internal
 */
class LandingPageControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testLandingPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/myUrl', []);

        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(LandingPageLoadedHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        /** @var ChannelEntity $channel */
        $channel = static::getContainer()->get('channel.repository')->search(
            (
                new Criteria())->addFilter(
                    new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND),
                    new EqualsFilter('domains.url', $_SERVER['APP_URL'])
                ),
            Context::createDefaultContext()
        )->getEntities()->first();

        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => true,
            'channels' => [
                [
                    'id' => $channel->getId(),
                ],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'blog_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'blog-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('landing_page.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
