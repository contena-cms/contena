<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Storefront\Page\Sitemap\SitemapPageLoadedHook;

/**
 * @internal
 */
#[Package('discovery')]
class SitemapControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testSitemapPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/sitemap.xml', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(SitemapPageLoadedHook::HOOK_NAME, $traces);
    }
}
