<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Integration\IntegrationCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class ApiRequestContextResolverAppTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private static string $fixturesPath = __DIR__ . '/../App/Manifest/_fixtures';

    public function testCanReadWithPermission(): void
    {
        $this->loadAppsFromDir(self::$fixturesPath . '/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($this->getBrowser(), 'test');

        $browser->jsonRequest('GET', '/api/blog');
        $response = $browser->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function testCantReadWithoutPermission(): void
    {
        $this->loadAppsFromDir(self::$fixturesPath . '/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->jsonRequest('GET', '/api/media');

        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testCantReadWithoutAnyPermission(): void
    {
        $this->loadAppsFromDir(self::$fixturesPath . '/minimal');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'minimal');

        $browser->jsonRequest('GET', '/api/blog');

        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testCanNotWriteWithoutPermissions(): void
    {
        $blogId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->loadAppsFromDir(self::$fixturesPath . '/minimal');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'minimal');

        $browser->jsonRequest(
            'POST',
            '/api/blog',
            $this->getBlogData($blogId, $context)
        );
        $response = $browser->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $data['errors'][0]['code']);
    }

    public function testCanWriteWithPermissionsSet(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->loadAppsFromDir(self::$fixturesPath . '/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->jsonRequest(
            'POST',
            '/api/blog',
            $this->getBlogData($blogId, $context)
        );

        static::assertSame(204, $browser->getResponse()->getStatusCode());

        $blog = $blogRepository->search(new Criteria(), $context)->getEntities()->get($blogId);

        static::assertNotNull($blog);
    }

    public function testItCanUpdateAnExistingBlog(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogId = Uuid::randomHex();
        $newName = 'i got a new name';
        $context = Context::createDefaultContext();

        $blogRepository->create([$this->getBlogData($blogId, $context)], $context);

        $this->loadAppsFromDir(self::$fixturesPath . '/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->jsonRequest(
            'PATCH',
            '/api/blog/' . $blogId,
            [
                'name' => $newName,
            ]
        );

        static::assertSame(204, $browser->getResponse()->getStatusCode());

        $blog = $blogRepository->search(new Criteria(), $context)->getEntities()->get($blogId);

        static::assertNotNull($blog);
        static::assertSame($newName, $blog->getName());
    }

    private function authorizeBrowserWithIntegrationByAppName(KernelBrowser $browser, string $appName): void
    {
        $app = $this->fetchApp($appName);
        if (!$app) {
            throw new \RuntimeException('No app found with name: ' . $appName);
        }

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $this->setAccessTokenForIntegration($app->getIntegrationId(), $accessKey, $secret);

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secret,
        ];

        $browser->jsonRequest('POST', '/api/oauth/token', $authPayload, $authPayload);

        static::assertIsString($browser->getResponse()->getContent());
        $data = json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        $accessToken = $data['access_token'];
        \assert(\is_string($accessToken));
        $browser->setServerParameter('HTTP_Authorization', \sprintf('Bearer %s', $accessToken));
    }

    /**
     * @return array{id: string, name: string, blogNumber: string, stock: int, manufacturer: array<string, string>, price: list<array<string, mixed>>, tax: array<string, string>}
     */
    private function getBlogData(string $blogId, Context $context): array
    {
        return [
            'id' => $blogId,
            'name' => 'created by integration',
            'blogNumber' => 'SWC-1000',
            'stock' => 100,
            'manufacturer' => [
                'name' => 'app creator',
            ],
            'price' => [
                [
                    'gross' => 100,
                    'net' => 200,
                    'linked' => false,
                    'currencyId' => $context->getCurrencyId(),
                ],
            ],
            'tax' => [
                'name' => 'luxury',
                'taxRate' => '25',
            ],
        ];
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    private function setAccessTokenForIntegration(string $integrationId, string $accessKey, string $secret): void
    {
        /** @var EntityRepository<IntegrationCollection> $integrationRepository */
        $integrationRepository = static::getContainer()->get('integration.repository');

        $integrationRepository->update([
            [
                'id' => $integrationId,
                'accessKey' => $accessKey,
                'secretAccessKey' => $secret,
            ],
        ], Context::createDefaultContext());
    }
}
