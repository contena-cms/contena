<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class LandingPageControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    private const string DEFAULT_LANDING_PAGE_ID = '43d1adaa1e699b09cb48643eadd87efb';

    private const string DEFAULT_LANDING_PAGE_LAYOUT_ID = '761d10258bef0a6e59e58b916428a2e4';

    public function testLandingPageRendersAssignedContentLayout(): void
    {
        $response = $this->request('GET', 'landingPage/' . self::DEFAULT_LANDING_PAGE_ID, []);
        $content = (string) $response->getContent();

        static::assertSame(200, $response->getStatusCode(), $content);
        static::assertStringContainsString('data-page-id="' . self::DEFAULT_LANDING_PAGE_LAYOUT_ID . '"', $content);
        static::assertStringContainsString('<h1>About Contena</h1>', $content);
    }
}
