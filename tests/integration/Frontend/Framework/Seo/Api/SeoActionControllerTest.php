<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 */
class SeoActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendChannelTestHelper;

    public function testValidateEmpty(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/validate');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);
        $result = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($result['errors']);
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

        static::assertNotEmpty($result['errors'] ?? []);
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

        static::assertNotEmpty($result['errors'] ?? []);
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
            'name' => 'test',
            'active' => true,
            'type' => 'post',
            'visibilities' => [[
                'channelId' => TestDefaults::CHANNEL,
                'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
            ]],
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
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');
        $this->createTestBlog($channelId);

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

    public function testPreviewForHeadlessChannelApiRoute(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext([
            'id' => $channelId,
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'name' => 'test',
            'domains' => [
                [
                    'url' => 'https://foo.bar',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'isExternalFrontend' => true,
                ],
            ],
        ]);

        $this->createTestBlog($channelId);

        $data = [
            'routeName' => BlogChannelApiUrlRoute::ROUTE_NAME,
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

    public function testPreviewForHeadlessChannelApiRouteWithEmptyTemplateIsNotInvalid(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => BlogChannelApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => '',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testPreviewForHeadlessChannelApiRouteWithFullUrlButNoEntities(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $data = [
            'routeName' => BlogChannelApiUrlRoute::ROUTE_NAME,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'template' => 'https://foo.bar/{{ blog.name }}',
            'channelId' => $channelId,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/_action/seo-url-template/preview', $data);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testGetSeoContextForHeadlessChannelApiRoute(): void
    {
        $channelId = Uuid::randomHex();
        $this->createChannelContext(['id' => $channelId, 'typeId' => Defaults::CHANNEL_TYPE_API, 'name' => 'test']);

        $this->createTestBlog($channelId);

        $data = ['routeName' => BlogChannelApiUrlRoute::ROUTE_NAME];
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

        static::assertSame(SeoException::SEO_URL_ROUTE_NOT_FOUND, $result['errors'][0]['code']);
    }

    public function testPreviewWithPrepareCriteriaMethodActiveBlogFiltering(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        // Create enough inactive blogs to test the limit=10 behavior.
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
     * @param array<string, mixed> $data
     */
    private function createTestBlog(string $channelId = TestDefaults::CHANNEL, array $data = []): string
    {
        $id = Uuid::randomHex();
        $blog = [
            'id' => $id,
            'name' => 'test',
            'active' => true,
            'type' => 'post',
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
