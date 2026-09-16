<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptControllerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testGetApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request('GET', 'frontend/script/json-response', []);
        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces($this->getFrontendRequestContainer());
        static::assertArrayHasKey('frontend-json-response', $traces);
        static::assertCount(1, $traces['frontend-json-response']);
        static::assertSame('some debug information', $traces['frontend-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertSame('bar', $body['foo']);
    }

    public function testGetApiEndpointWithSlashInHookName(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request('GET', 'frontend/script/json/response', []);
        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces($this->getFrontendRequestContainer());
        static::assertArrayHasKey('frontend-json-response', $traces);
        static::assertCount(1, $traces['frontend-json-response']);
        static::assertSame('some debug information', $traces['frontend-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertSame('bar', $body['foo']);
    }

    public function testPostApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request(
            'POST',
            'frontend/script/json-response',
            $this->tokenize('frontend.script_endpoint', []),
        );

        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces($this->getFrontendRequestContainer());
        static::assertArrayHasKey('frontend-json-response', $traces);
        static::assertCount(1, $traces['frontend-json-response']);
        static::assertSame('some debug information', $traces['frontend-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertSame('bar', $body['foo']);
    }

    public function testRenderTemplate(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createBlogs($ids);

        $response = $this->request(
            'GET',
            'frontend/script/render?blog-id=' . $ids->get('b1'),
            [],
        );

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertStringContainsString('My Test Blog', $response->getContent());
        static::assertSame('text/plain; charset=UTF-8', $response->headers->get('content-type'));
    }

    public function testRenderTemplateThroughASeoUrlCarryingTheQueryParameters(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createBlogs($ids);

        $connection = static::getContainer()->get(Connection::class);
        $domain = $connection->fetchAssociative(
            'SELECT `channel_id`, `language_id` FROM `channel_domain` WHERE `url` = :url',
            ['url' => EnvironmentHelper::getVariable('APP_URL')],
        );
        static::assertIsArray($domain);

        $connection->insert('seo_url', [
            'data_scope_id' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
            'id' => Uuid::randomBytes(),
            'channel_id' => $domain['channel_id'],
            'language_id' => $domain['language_id'],
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => 'frontend.app.test.render',
            'path_info' => '/frontend/script/render?blog-id=' . $ids->get('b1'),
            'seo_path_info' => 'my-render-page',
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        $response = $this->request('GET', 'my-render-page', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertStringContainsString('My Test Blog', $response->getContent());
    }

    public function testRedirectResponseTemplate(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createBlogs($ids);

        $response = $this->request(
            'GET',
            'frontend/script/redirect-response?blog-id=' . $ids->get('b1'),
            [],
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/blog/' . $ids->get('b1'), $response->headers->get('location'));
    }

    #[DataProvider('ensureLoginProvider')]
    public function testEnsureLogin(bool $login, int $expectedStatus, string $expectedResponse): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $browser = $login
            ? $this->registerInBrowser()
            : KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request(
            'GET',
            EnvironmentHelper::getVariable('APP_URL') . '/frontend/script/ensure-login',
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame($expectedStatus, $response->getStatusCode(), $response->getContent());
        static::assertStringContainsString($expectedResponse, $response->getContent());
    }

    public static function ensureLoginProvider(): \Generator
    {
        yield 'Not logged in' => [
            false,
            Response::HTTP_UNAUTHORIZED,
            'A logged-in member is required for this operation.',
        ];

        yield 'Logged in' => [
            true,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];
    }

    private function createBlogs(IdsCollection $ids): void
    {
        $blog = new BlogBuilder($ids, 'b1')
            ->name('My Test Blog')
            ->visibility($this->getChannelId())
            ->build();

        /** @var EntityRepository<BlogCollection> $repository */
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([$blog], Context::createDefaultContext());
    }

    private function registerInBrowser(): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            EnvironmentHelper::getVariable('APP_URL') . '/account/register',
            $this->tokenize('frontend.account.register.save', [
                'email' => 'max.mustermann@example.com',
                'emailConfirmation' => 'max.mustermann@example.com',
                'name' => 'Max Mustermann',
                'phoneNumber' => '123456789',
                'password' => 'contenaAdmin',
            ]),
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        return $browser;
    }
}
