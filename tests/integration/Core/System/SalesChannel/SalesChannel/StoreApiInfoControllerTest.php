<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('store-api')]
class StoreApiInfoControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testFetchStoreApiRoutes(): void
    {
        $client = $this->getSalesChannelBrowser();
        $client->request('GET', '/store-api/_info/routes');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }
}
