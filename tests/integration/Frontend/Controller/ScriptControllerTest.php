<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptControllerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testGetApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request('GET', '/frontend/script/json-response', []);
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

        $response = $this->request('GET', '/frontend/script/json/response', []);
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
            '/frontend/script/json-response',
            $this->tokenize('frontend.script_endpoint', [])
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
            '/frontend/script/render?blog-id=' . $ids->get('p1'),
            []
        );

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString('My Test-Blog', $response->getContent());
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
            ['url' => EnvironmentHelper::getVariable('APP_URL')]
        );
        static::assertIsArray($domain);

        $connection->insert('seo_url', [
            'id' => Uuid::randomBytes(),
            'channel_id' => $domain['channel_id'],
            'language_id' => $domain['language_id'],
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => 'frontend.app.test.render',
            'path_info' => '/frontend/script/render?blog-id=' . $ids->get('p1'),
            'seo_path_info' => 'my-render-page',
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        $response = $this->request('GET', 'my-render-page', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertStringContainsString('My Test-Blog', $response->getContent());
    }

    public function testRedirectResponseTemplate(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createBlogs($ids);

        $response = $this->request(
            'GET',
            '/frontend/script/redirect-response?blog-id=' . $ids->get('p1'),
            []
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/detail/' . $ids->get('p1'), $response->headers->get('location'));
    }

    #[DataProvider('ensureLoginProvider')]
    public function testEnsureLogin(bool $login, bool $isGuest, bool $allowGuest, int $expectedStatus, string $expectedResponse): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        if ($login) {
            $browser = $this->registerInBrowser($isGuest);
        } else {
            $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        }

        $browser->request(
            'GET',
            EnvironmentHelper::getVariable('APP_URL') . '/frontend/script/ensure-login?allow-guest=' . (int) $allowGuest
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame($expectedStatus, $response->getStatusCode());
        static::assertStringContainsString($expectedResponse, $response->getContent());
    }

    public static function ensureLoginProvider(): \Generator
    {
        yield 'Not logged in' => [
            false,
            false,
            false,
            Response::HTTP_FORBIDDEN,
            'User is not logged in.',
        ];

        yield 'Logged in as guest, but guest not allowed' => [
            true,
            true,
            false,
            Response::HTTP_FORBIDDEN,
            'User is not logged in.',
        ];

        yield 'Logged in as guest and guest is allowed' => [
            true,
            true,
            true,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];

        yield 'Logged in and guest is allowed' => [
            true,
            false,
            true,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];

        yield 'Logged in and guest is not allowed' => [
            true,
            false,
            false,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];
    }

    private function createBlogs(IdsCollection $ids): void
    {
        $blog1 = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->name('My Test-Blog')
            ->manufacturer('m1')
            ->variant(
                (new BlogBuilder($ids, 'v1.1'))
                    ->build()
            );

        $channelIds = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        foreach ($channelIds as $channelId) {
            $blog1->visibility($channelId);
        }

        static::getContainer()->get('blog.repository')->create([
            $blog1->build(),
        ], Context::createDefaultContext());
    }

    private function registerInBrowser(bool $isGuest): KernelBrowser
    {
        $data = $this->getRegistrationData($isGuest);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        return $browser;
    }

    /**
     * @return array<string, string|bool|array<string, string>>
     */
    private function getRegistrationData(bool $isGuest): array
    {
        $data = [
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'emailConfirmation' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'frontendUrl' => 'http://localhost',

            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if (!$isGuest) {
            $data['createUserAccount'] = true;
            $data['password'] = TestDefaults::HASHED_PASSWORD;
        }

        return $data;
    }
}
