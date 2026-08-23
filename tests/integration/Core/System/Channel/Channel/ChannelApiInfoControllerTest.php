<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Group('channel-api')]
class ChannelApiInfoControllerTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testFetchChannelApiRoutes(): void
    {
        $browser = $this->getChannelBrowser();
        $browser->request('GET', '/channel-api/_info/routes');

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);
        static::assertJson($content);
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('endpoints', $routes);

        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }
}
