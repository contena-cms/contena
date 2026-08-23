<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Response\Type\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Api\Response\Type\Api\JsonApiType;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\Api\StructEncoder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(JsonApiType::class)]
final class JsonApiTypeTest extends TestCase
{
    public function testCreateListingResponseEncodesEntities(): void
    {
        $definition = new BlogDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $blog = new BlogEntity()->assign([
            'id' => 'blog-id',
            '_uniqueIdentifier' => 'blog-id',
        ]);

        $searchResult = new EntitySearchResult(
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $type = new JsonApiType(new JsonApiEncoder(), static::createStub(StructEncoder::class));

        $response = $type->createListingResponse(
            new Criteria(),
            $searchResult,
            $definition,
            Request::create('/api/blog'),
            Context::createDefaultContext()
        );

        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $decoded['meta']['total']);
        static::assertCount(1, $decoded['data']);
        static::assertSame('blog-id', $decoded['data'][0]['id']);
        static::assertSame(BlogDefinition::ENTITY_NAME, $decoded['data'][0]['type']);
    }

    public function testCreateListingResponseAddsLastLinkBeforeNextPagesSentinel(): void
    {
        $criteria = new Criteria()
            ->setLimit(2)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $response = $this->createListingResponse($criteria, 12);

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('http://localhost/api/blog?limit=2&page=6', $decoded['links']['last']);
    }

    public function testCreateListingResponseOmitsLastLinkAtNextPagesSentinel(): void
    {
        $criteria = new Criteria()
            ->setLimit(2)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $response = $this->createListingResponse($criteria, 13);

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('last', $decoded['links']);
        static::assertSame('http://localhost/api/blog?limit=2&page=2', $decoded['links']['next']);
    }

    private function createListingResponse(Criteria $criteria, int $total): Response
    {
        $definition = new BlogDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $searchResult = new EntitySearchResult(
            $total,
            new BlogCollection(),
            null,
            $criteria,
            Context::createDefaultContext()
        );

        return new JsonApiType(new JsonApiEncoder(), static::createStub(StructEncoder::class))->createListingResponse(
            $criteria,
            $searchResult,
            $definition,
            Request::create('/api/blog'),
            Context::createDefaultContext()
        );
    }
}
