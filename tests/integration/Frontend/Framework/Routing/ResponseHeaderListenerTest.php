<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Routing\NotFound\NotFoundSubscriber;

/**
 * @internal
 */
class ResponseHeaderListenerTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    public function testHomeController(): void
    {
        $browser = $this->createCustomChannelBrowser();
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, '1234');
        $browser->request('GET', '/');
        $response = $browser->getResponse();

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testNotFoundPage(): void
    {
        try {
            $this->toggleNotFoundSubscriber(false);
            $browser = $this->createCustomChannelBrowser();
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
            $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

            $browser->request('GET', '/not-found');
            $response = $browser->getResponse();

            static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertFalse($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
            static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
        } finally {
            $this->toggleNotFoundSubscriber(true);
        }
    }

    public function testChannelApiPresent(): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => TestDefaults::CHANNEL,
            'languages' => [],
        ]);
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_VERSION_ID, '1234');
        $browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID, Uuid::randomHex());
        $browser->request('GET', '/channel-api/context');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_VERSION_ID));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    /**
     * We need to enable the not found subscriber so the 404 page is rendered.
     * That is not enabled by default in the test environment as APP_DEBUG is set to false.
     */
    private function toggleNotFoundSubscriber(bool $debug): void
    {
        $subscriber = static::getContainer()->get(NotFoundSubscriber::class);
        new \ReflectionProperty($subscriber::class, 'kernelDebug')->setValue($subscriber, $debug);
    }
}
