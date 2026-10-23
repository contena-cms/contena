<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoadedHook;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class MaintenanceControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testMaintenancePageLoadedHookScriptsAreExecuted(): void
    {
        $this->setMaintenanceMode();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->followRedirects();

        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/');
        $response = $browser->getResponse();

        static::assertSame(503, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MaintenancePageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMaintenancePageLoadedHookScriptsAreExecutedForSinglePage(): void
    {
        $response = $this->request('GET', '/maintenance/singlepage/' . $this->ids->get('page'), []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MaintenancePageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMaintenancePageIsRenderedForClientNotInAllowlist(): void
    {
        // a configured allowlist that does not contain the requesting client must still render the maintenance page;
        // this exercises the allowlist IP header handling (DomainLoader -> RequestTransformer -> controller)
        $this->setMaintenanceMode(['10.253.0.1']);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->followRedirects();
        $browser->request('GET', EnvironmentHelper::getVariable('APP_URL') . '/');
        $response = $browser->getResponse();

        static::assertSame(503, $response->getStatusCode());
    }

    private function createData(): void
    {
        $page = [
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
        ];

        static::getContainer()->get('cms_page.repository')->create([$page], Context::createDefaultContext());
    }

    /**
     * @param list<string>|null $allowlist
     */
    private function setMaintenanceMode(?array $allowlist = null): void
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $channel = $channelRepository->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($channel);

        $update = [
            'id' => $channel->getId(),
            'maintenance' => true,
        ];

        if ($allowlist !== null) {
            $update['maintenanceIpAllowlist'] = $allowlist;
        }

        $channelRepository->update([$update], Context::createDefaultContext());

        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.maintenancePage', $this->ids->get('page'));
    }
}
