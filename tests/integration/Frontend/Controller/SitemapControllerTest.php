<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Frontend\Page\Sitemap\SitemapPageLoadedHook;

/**
 * @internal
 */
class SitemapControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testSitemapPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/sitemap.xml', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getFrontendRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SitemapPageLoadedHook::HOOK_NAME, $traces);
    }
}
