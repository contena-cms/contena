<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * @internal
 */
final class ResponseTypeRegistryTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private ResponseFactoryRegistry $responseRegistry;

    protected function setUp(): void
    {
        $this->responseRegistry = static::getContainer()->get(ResponseFactoryRegistry::class);
    }

    public function getAdminContext(): Context
    {
        return new Context(new AdminApiSource(Uuid::randomHex()));
    }

    public function testAdminApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/json';
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, '/api/category/' . $id, $accept, false);

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($id, $content['data']['name']);
    }

    public function testAdminJsonApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertSame($id, $content['data']['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self, $content['data']['links']['self']);
    }

    public function testAdminJsonApiDefault(): void
    {
        $id = Uuid::randomHex();
        $accept = '*/*';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertSame('application/vnd.api+json', $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertSame($id, $content['data']['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self, $content['data']['links']['self']);
    }

    public function testAdminApiUnsupportedContentType(): void
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $id = Uuid::randomHex();
        $accept = 'text/plain';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $this->getDetailResponse($context, $id, $self, $accept, false);
    }

    public function testAdminJsonApiList(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/category';
        $context = $this->getAdminContext();
        $response = $this->getListResponse($context, $id, $self, $accept);

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertNotEmpty($content['data']);
        static::assertSame($id, $content['data'][0]['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function assertDetailJsonApiStructure(array $content): void
    {
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('links', $content);
        static::assertArrayHasKey('included', $content);
    }

    private function getDetailResponse(Context $context, string $id, string $path, string $accept, bool $setLocationHeader): Response
    {
        $category = $this->getTestCategory($id);

        $definition = static::getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $this->getFactory($request)->createDetailResponse(new Criteria(), $category, $definition, $request, $context, $setLocationHeader);
    }

    private function getListResponse(Context $context, string $id, string $path, string $accept): Response
    {
        $category = $this->getTestCategory($id);

        $collection = new CategoryCollection([$category]);
        $criteria = new Criteria();
        /** @var EntitySearchResult<CategoryCollection> $searchResult */
        $searchResult = new EntitySearchResult(1, $collection, null, $criteria, $context);

        $definition = static::getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $this->getFactory($request)->createListingResponse($criteria, $searchResult, $definition, $request, $context);
    }

    private function getTestCategory(string $id): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName($id);
        $category->internalSetEntityData('category', new FieldVisibility([]));

        return $category;
    }

    private function getFactory(Request $request): ResponseFactoryInterface
    {
        return $this->responseRegistry->getType($request);
    }
}
