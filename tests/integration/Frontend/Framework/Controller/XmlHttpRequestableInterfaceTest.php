<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;

/**
 * @internal
 */
class XmlHttpRequestableInterfaceTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    public function testPageLoads(): void
    {
        $client = $this->createChannelBrowser(null, true);
        $client->request('GET', 'http://localhost/');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAccessDeniedForXmlHttpRequest(): void
    {
        $client = $this->createChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', 'http://localhost/');

        static::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoads(): void
    {
        $client = $this->createChannelBrowser(null, true);

        $client->request('GET', 'http://localhost/checkout/offcanvas');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoadsForXmlHttpRequest(): void
    {
        $client = $this->createChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', 'http://localhost/checkout/offcanvas');

        static::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
