<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('channel-api')]
class ContextRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testFetchingContext(): void
    {
        $browser = $this->getChannelBrowser();
        $browser->request('GET', '/channel-api/context');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('channel', $response);
        static::assertSame($this->getChannelApiChannelId(), $response['channel']['id']);
    }

    public function testFetchingContextWithMember(): void
    {
        $this->login();

        $browser = $this->getChannelBrowser();
        $browser->request('GET', '/channel-api/context');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('channel', $response);
        static::assertSame($this->getChannelApiChannelId(), $response['channel']['id']);
        static::assertArrayHasKey('member', $response);
        static::assertIsArray($response['member']);
    }
}
