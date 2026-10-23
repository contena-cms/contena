<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 *
 * @phpstan-type Blog array{id: string, attributes: array{isModified: boolean, seoPathInfo: string } }
 */
class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendChannelTestHelper;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `order`');
        $connection->executeStatement('DELETE FROM user');
        $connection->executeStatement('DELETE FROM blog');
        $connection->executeStatement('DELETE FROM channel');
    }

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($result['errors']);
        static::assertNotCount(0, $result['errors']);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateInvalidTwigSyntax(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName(BlogPageSeoUrlRoute::ROUTE_NAME);
        $template->setTemplate('{{ blog.name }');
        $template->setEntityName(static::getContainer()->get(BlogDefinition::class)->getEntityName());
        $template->setChannelId(TestDefaults::CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $errors = $result['errors'] ?? [];
        static::assertIsArray($errors);
        static::assertNotCount(0, $errors);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateInvalidDataUsage(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName(BlogPageSeoUrlRoute::ROUTE_NAME);
        $template->setTemplate('{{ blog.undefinedProperty }}');
        $template->setEntityName(static::getContainer()->get(BlogDefinition::class)->getEntityName());
        $template->setChannelId(TestDefaults::CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $errors = $result['errors'] ?? [];
        static::assertIsArray($errors);
        static::assertNotCount(0, $errors);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateValid(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $this->createTestBlog($channelId);
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName(BlogPageSeoUrlRoute::ROUTE_NAME);
        $template->setTemplate('{{ blog.name }}');
        $template->setEntityName(BlogDefinition::ENTITY_NAME);
        $template->setChannelId($channelId);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $result);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testGetSeoContext(): void
    {
        $blog = [
            'id' => Uuid::randomHex(),
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 20,
                    'linked' => false,
                ],
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/blog', $blog);

        $data = [
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotNull($data['blog'] ?? null);
    }

    public function testPreview(): void
    {
        $this->createFrontendChannelContext(TestDefaults::CHANNEL, 'test');
        $this->createTestBlog();

        $data = [
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '{{ blog.name }}',
            'channelId' => TestDefaults::CHANNEL,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('test', $data[0]['seoPathInfo']);
    }

    public function testPreviewWithBrokenTemplate(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');
        $this->createTestBlog($channelId);

        $data = [
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '{{ blog.undefinedProperty }}',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();

        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('FRAMEWORK__INVALID_SEO_TEMPLATE', $data['errors'][0]['code']);
    }

    public function testPreviewWithChannel(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $aId = $this->createTestCategory('A');
        $this->createTestCategory('B', $aId);

        $this->updateChannelNavigationEntryPoint($channelId, $aId);

        $data = [
            'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(CategoryDefinition::class)->getEntityName(),
            'template' => NavigationPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $urls = array_column($data, 'seoPathInfo');
        static::assertContains('B/', $urls);
    }

    public function testPreviewForHeadlessStoreApiRoute(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext([
            'id' => $channelId,
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'name' => 'test',
            'domains' => [
                [
                    'url' => 'https://foo.bar',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'isExternalFrontend' => true,
                ],
            ],
        ]);

        $this->createTestBlog($channelId);

        $data = [
            'routeName' => BlogStoreApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '{{ blog.name }}',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('https://foo.bar/test', $data[0]['seoPathInfo']);
    }

    public function testPreviewForHeadlessStoreApiRouteWithEmptyTemplateIsNotInvalid(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => BlogStoreApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testPreviewForHeadlessStoreApiRouteWithFullUrlButNoEntities(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => BlogStoreApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => 'https://foo.bar/{{ blog.name }}',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testGetSeoContextForHeadlessStoreApiRoute(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $this->createTestBlog($channelId);

        // The store-api route name resolves to the blog definition via the tagged entity routes; no entityName needed.
        $data = ['routeName' => BlogStoreApiUrlRoute::ROUTE_NAME];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/context', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($data['blog'] ?? null);
    }

    public function testUnknownRoute(): void
    {
        $template = new SeoUrlTemplateEntity();
        $template->setRouteName('unknown.route');
        $template->setTemplate('{{ blog.name }}');
        $template->setEntityName(static::getContainer()->get(BlogDefinition::class)->getEntityName());
        $template->setChannelId(TestDefaults::CHANNEL);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate', $template->jsonSerialize());
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $result);
        static::assertSame(404, $response->getStatusCode());

        $expectedErrorCode = SeoException::SEO_URL_ROUTE_NOT_FOUND;
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedErrorCode = SeoUrlRouteNotFoundException::ERROR_CODE;
        }

        static::assertSame($expectedErrorCode, $result['errors'][0]['code']);
    }

    public function testUpdateDefaultCanonical(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestBlog($channelId);

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;

        // modify canonical
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);

        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);

        $blogUpdate = [
            'id' => $id,
            'name' => 'unused name',
        ];
        $this->getBrowser()->request('PATCH', '/api/blog/' . $id, $blogUpdate);

        // seo url is not updated with the blog
        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);
    }

    /**
     * Regression for contena/contena#4413: a write-protected (isModified=true) canonical
     * SEO URL must be editable again and resettable to the template-generated path.
     */
    public function testUpdateWriteProtectedCanonicalCanBeEditedAndReset(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestBlog($channelId);

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        $initialPathInfo = $seoUrls[0]['attributes']['seoPathInfo'];

        // Initial manual modification – writes a write-protected URL.
        $manualPath = 'manual-path';
        $firstPayload = $seoUrls[0]['attributes'];
        $firstPayload['seoPathInfo'] = $manualPath;
        $firstPayload['isModified'] = true;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $firstPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        static::assertTrue($seoUrls[0]['attributes']['isModified']);
        static::assertSame($manualPath, $seoUrls[0]['attributes']['seoPathInfo']);

        // (a) Manual edit of the already write-protected SEO URL must persist.
        $editedPath = 'manual-path-edited';
        $editPayload = $seoUrls[0]['attributes'];
        $editPayload['seoPathInfo'] = $editedPath;
        $editPayload['isModified'] = true;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $editPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        static::assertTrue($seoUrls[0]['attributes']['isModified']);
        static::assertSame($editedPath, $seoUrls[0]['attributes']['seoPathInfo']);

        // (b) Clearing the write-protection flag must also actually clear it on the
        // persisted canonical (previously the overwrite check silently kept isModified=true).
        $clearPayload = $seoUrls[0]['attributes'];
        $clearPayload['seoPathInfo'] = 'manual-path-final';
        $clearPayload['isModified'] = false;

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $clearPayload);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        $canonical = null;
        foreach ($seoUrls as $url) {
            if ($url['attributes']['isCanonical'] ?? true) {
                $canonical = $url['attributes'];
                break;
            }
        }

        static::assertNotNull($canonical, 'A canonical SEO URL must still exist after clearing the write-protection flag');
        static::assertFalse($canonical['isModified'], 'Canonical SEO URL must be cleared of the write-protection flag');
        static::assertSame('manual-path-final', $canonical['seoPathInfo']);

        // The original template-generated path is still reachable (kept as a
        // redirecting non-canonical entry so old links keep working).
        static::assertNotCount(
            0,
            array_filter(
                $this->getSeoUrls($id, null, $channelId),
                static fn (array $url): bool => $url['attributes']['seoPathInfo'] === $initialPathInfo
            ),
            'Original template path must be retained as a non-canonical redirect'
        );
    }

    /**
     * Regression for contena/contena#4413 (same-path reset): a write-protected canonical whose
     * path already equals the template output must still be resettable through the admin endpoint.
     * Clearing the flag without changing the path must actually drop isModified instead of silently
     * keeping it write-protected.
     *
     * The write-protected canonical is seeded directly so the test does not depend on frontend
     * blog SEO indexing, but the reset itself goes through the real `/api/_action/seo-url/canonical`
     * endpoint (validation + SeoUrlPersister + DB).
     */
    public function testResetWriteProtectedCanonicalWithUnchangedPath(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');
        $blogId = $this->createTestBlog($channelId);

        $route = BlogPageSeoUrlRoute::ROUTE_NAME;
        $path = 'red-shoe';

        // Normalise: drop any auto-generated SEO URLs for the blog so the seeded
        // write-protected canonical is the only row, regardless of whether the environment
        // generated one on blog creation.
        $connection->executeStatement(
            'DELETE FROM seo_url WHERE foreign_key = :fk',
            ['fk' => Uuid::fromHexToBytes($blogId)]
        );

        // Seed a write-protected canonical whose path already equals the template output
        // (mimics a migrated/manually created SEO URL).
        $connection->insert('seo_url', [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'channel_id' => Uuid::fromHexToBytes($channelId),
            'foreign_key' => Uuid::fromHexToBytes($blogId),
            'route_name' => $route,
            'path_info' => '/detail/' . $blogId,
            'seo_path_info' => $path,
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        // Reset through the admin endpoint: same path, isModified=false.
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'foreignKey' => $blogId,
            'routeName' => $route,
            'pathInfo' => '/detail/' . $blogId,
            'seoPathInfo' => $path,
            'channelId' => $channelId,
            'isModified' => false,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $canonicals = $connection->fetchAllAssociative(
            'SELECT seo_path_info, is_modified FROM seo_url WHERE foreign_key = :fk AND is_canonical = 1',
            ['fk' => Uuid::fromHexToBytes($blogId)]
        );

        static::assertCount(1, $canonicals, 'Exactly one canonical SEO URL must remain');
        static::assertSame($path, $canonicals[0]['seo_path_info']);
        static::assertSame(
            0,
            (int) $canonicals[0]['is_modified'],
            'Write-protection flag must be cleared even when the path did not change'
        );
    }

    public function testUpdateCanonicalWithCustomChannel(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestBlog($channelId);

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);

        $seoUrl = $seoUrls[0]['attributes'];
        static::assertFalse($seoUrl['isModified']);

        $newSeoPathInfo = 'my-awesome-seo-path';
        $seoUrl['seoPathInfo'] = $newSeoPathInfo;
        $seoUrl['isModified'] = true;
        $seoUrl['channelId'] = $channelId;

        // modify canonical
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);

        $newBlogNumber = Uuid::randomHex();
        $blogUpdate = [
            'id' => $id,
            'name' => 'updated-name',
            'blogNumber' => $newBlogNumber,
        ];
        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $id, $blogUpdate);

        // seoPathInfo for the custom channel is not updated with the blog
        $seoUrls = $this->getSeoUrls($id, true, $channelId);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls[0]['attributes'];
        static::assertTrue($seoUrl['isModified']);
        static::assertSame($newSeoPathInfo, $seoUrl['seoPathInfo']);
    }

    public function testUpdateDefaultCanonicalForHeadlessBehavesCorrectly(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext([
            'id' => $channelId,
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'name' => 'test',
            'domains' => [
                [
                    'url' => 'https://foo.bar',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'isExternalFrontend' => true,
                ],
            ],
        ]);

        $id = $this->createTestBlog($channelId);

        $seoUrls = $this->getSeoUrls($id, true, $channelId);

        static::assertCount(1, $seoUrls);

        $seoUrl = [
            'foreignKey' => $id,
            'seoPathInfo' => 'my-awesome-seo-path',
            'pathInfo' => '/store-api/blog/' . $id,
            'channelId' => $channelId,
            'isModified' => true,
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
        ];

        // modify canonical
        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', $seoUrl);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $seoUrls = $this->getSeoUrls($id, true, $channelId);

        static::assertCount(1, $seoUrls);

        $blogUpdate = [
            'id' => $id,
            'name' => 'unused name',
        ];
        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $id, $blogUpdate);

        $seoUrls = $this->getSeoUrls($id, true, $channelId);

        static::assertCount(1, $seoUrls);
    }

    public function testPreviewWithPrepareCriteriaMethodActiveBlogFiltering(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        // We need to create enough inactive blogs to test the limit=10 behavior
        $inactiveBlogIds = [];
        for ($i = 1; $i <= 10; ++$i) {
            $inactiveBlogId = $this->createTestBlog($channelId, ['name' => "Inactive Blog $i", 'active' => false]);
            $inactiveBlogIds[] = $inactiveBlogId;
        }

        // Create an active blog that should be returned
        $activeBlogId = $this->createTestBlog($channelId);
        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $activeBlogId, [
            'id' => $activeBlogId,
            'name' => 'Active Blog',
            'active' => true,
        ]);

        $data = [
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '{{ blog.name }}',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $data, 'Should return exactly 1 active blog (prepareCriteria filters out inactive blogs)');

        $foreignKeys = array_column($data, 'foreignKey');
        static::assertContains($activeBlogId, $foreignKeys, 'Active blog should be included');

        foreach ($inactiveBlogIds as $inactiveBlogId) {
            static::assertNotContains($inactiveBlogId, $foreignKeys, "Inactive blog $inactiveBlogId should be filtered out by prepareCriteria");
        }
    }

    /**
     * @return array<Blog>
     */
    private function getSeoUrls(string $id, ?bool $canonical = null, ?string $channelId = null): array
    {
        $params = [];
        if ($canonical !== null) {
            $params = [
                'filter' => [
                    'isCanonical' => $canonical,
                    'channelId' => $channelId,
                ],
            ];
        }
        $this->getBrowser()->request('GET', '/api/blog/' . $id . '/seoUrls', $params);
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createTestBlog(string $channelId = TestDefaults::CHANNEL, array $data = []): string
    {
        $id = Uuid::randomHex();
        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'name' => 'test',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 20,
                    'linked' => false,
                ],
            ],
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'test',
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 0,
            'visibilities' => [
                [
                    'channelId' => $channelId,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/blog', array_merge($blog, $data));

        return $id;
    }

    private function createTestCategory(string $name, ?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $blog = [
            'id' => $id,
            'name' => $name,
            'parentId' => $parentId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/category', $blog);

        return $id;
    }
}
