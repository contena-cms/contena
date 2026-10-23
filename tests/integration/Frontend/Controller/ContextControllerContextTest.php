<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;

/**
 * @internal
 */
class ContextControllerContextTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private KernelBrowser $browser;

    private string $testBaseUrl;

    private string $defaultBaseUrl;

    private string $languageId;

    private Router $router;

    protected function setUp(): void
    {
        $this->router = static::getContainer()->get('router');

        $this->languageId = Uuid::randomHex();
        $localeId = Uuid::randomHex();

        $this->defaultBaseUrl = $_SERVER['APP_URL'];
        $this->testBaseUrl = $_SERVER['APP_URL'] . '/tst-TST';

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM channel');

        $domains = [
            [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->defaultBaseUrl,
            ],
            [
                'language' => [
                    'id' => $this->languageId,
                    'name' => 'Test',
                    'active' => true,
                    'locale' => [
                        'id' => $localeId,
                        'name' => 'Test',
                        'code' => 'af_ZA',
                        'territory' => 'test',
                    ],
                    'translationCodeId' => $localeId,
                ],
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $this->testBaseUrl,
            ],
        ];

        $this->browser = $this->createCustomChannelBrowser([
            'domains' => $domains,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->languageId]],
        ]);
    }

    protected function tearDown(): void
    {
        $this->router->getContext()->setBaseUrl('');
    }

    public function testSwitchToUpperCasePath(): void
    {
        $this->browser->request('GET', $this->defaultBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->defaultBaseUrl . '/checkout/language',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->testBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchFromUpperCasePath(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchWithWrongRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => 'frontend.homer.page']
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/', $response->headers->get('Location'));
    }

    public function testSwitchWithBlogIdAndCorrectRedirectTo(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        $blogId = Uuid::randomHex();

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/language',
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => BlogPageSeoUrlRoute::ROUTE_NAME, 'redirectParameters' => ['blogId' => $blogId]]
        );

        $response = $this->browser->getResponse();
        static::assertSame(302, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->defaultBaseUrl . '/detail/' . $blogId, $response->headers->get('Location'));
    }

    public function testConfigure(): void
    {
        $this->browser->request('GET', $this->testBaseUrl);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $contextSubscriber = new ContextControllerTestSubscriber();
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($contextSubscriber);

        $this->browser->request(
            'POST',
            $this->testBaseUrl . '/checkout/configure',
            ['languageId' => $this->languageId]
        );

        $response = $this->browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertSame($this->languageId, $contextSubscriber->switchEvent->getRequestDataBag()->get('languageId'));
    }
}

/**
 * @internal
 */
class ContextControllerTestSubscriber implements EventSubscriberInterface
{
    public ChannelContextSwitchEvent $switchEvent;

    public static function getSubscribedEvents(): array
    {
        return [
            ChannelContextSwitchEvent::class => 'onSwitch',
        ];
    }

    public function onSwitch(ChannelContextSwitchEvent $event): void
    {
        $this->switchEvent = $event;
    }
}
