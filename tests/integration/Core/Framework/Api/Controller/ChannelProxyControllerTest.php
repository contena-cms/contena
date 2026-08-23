<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class ChannelProxyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testProxyWithInvalidChannelId(): void
    {
        $this->getBrowser()->request('GET', $this->getUrl(Uuid::randomHex(), '/search'));

        $content = (string) $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode(), $content);
        static::assertSame('FRAMEWORK__INVALID_CHANNEL', $response['errors'][0]['code'] ?? null, $content);
    }

    public function testProxyCallToChannelApi(): void
    {
        $channel = $this->createChannel();

        $this->getBrowser()->request('GET', $this->getUrl($channel['id'], '/search?search=blog'));

        $content = (string) $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), $content);
        static::assertArrayNotHasKey('errors', $response, $content);
    }

    public function testHeadersAreCopied(): void
    {
        $channel = $this->createChannel();
        $contextToken = Uuid::randomHex();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($channel['id'], '/search?search=blog'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
                'HTTP_SW_LANGUAGE_ID' => Defaults::LANGUAGE_SYSTEM,
                'HTTP_SW_VERSION_ID' => Uuid::randomHex(),
            ],
        );

        static::assertSame($contextToken, $this->getBrowser()->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($contextToken, $this->getBrowser()->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSearchProxyCreatesAdminChannelContextInExplainMode(): void
    {
        $channel = $this->createChannel();
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $capturedRequest = null;

        $listener = static function (ControllerEvent $event) use (&$capturedRequest): void {
            if ($event->getRequest()->getPathInfo() === '/channel-api/search') {
                $capturedRequest = $event->getRequest();
            }
        };
        $dispatcher->addListener(KernelEvents::CONTROLLER, $listener);

        try {
            $this->getBrowser()->request('GET', $this->getUrl($channel['id'], '/search?search=blog'));
        } finally {
            $dispatcher->removeListener(KernelEvents::CONTROLLER, $listener);
        }

        static::assertInstanceOf(Request::class, $capturedRequest);

        $context = $capturedRequest->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(AdminChannelApiSource::class, $context->getSource());
        static::assertSame($channel['id'], $context->getSource()->getChannelId());
        static::assertTrue($context->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE));
    }

    private function getUrl(string $channelId, string $url): string
    {
        return \sprintf(
            '/api/_proxy/channel-api/%s/%s',
            $channelId,
            ltrim($url, '/'),
        );
    }
}
