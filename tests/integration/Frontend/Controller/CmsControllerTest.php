<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Cms\CmsPageLoadedHook;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class CmsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testCmsPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/cms/' . $this->ids->get('page'), []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CmsPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCmsPageLoadedHookScriptsAreExecutedForFullPage(): void
    {
        $response = $this->request('GET', '/page/cms/' . $this->ids->get('page'), []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CmsPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCmsPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/widgets/cms/navigation/' . $this->ids->get('category'), []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CmsPageLoadedHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'type' => 'landing_page',
            'cmsPage' => [
                'id' => $this->ids->create('page'),
                'name' => 'test page',
                'type' => 'landingpage',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $this->ids->create('slot1'),
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => $this->ids->create('slot2'),
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }
}
