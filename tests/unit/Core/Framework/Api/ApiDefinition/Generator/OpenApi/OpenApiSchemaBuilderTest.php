<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Response as OpenApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(OpenApiSchemaBuilder::class)]
class OpenApiSchemaBuilderTest extends TestCase
{
    public function testEnrichAddsDefaultErrorResponses(): void
    {
        $openApi = new OpenApi([]);

        new OpenApiSchemaBuilder('6.7.0.0')->enrich($openApi, DefinitionService::API);

        $responses = $this->getResponsesByStatusCode($openApi);

        foreach ([
            Response::HTTP_BAD_REQUEST => 'Bad Request',
            Response::HTTP_UNAUTHORIZED => 'Unauthorized',
            Response::HTTP_FORBIDDEN => 'Forbidden',
            Response::HTTP_NOT_FOUND => 'Not Found',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
            Response::HTTP_NO_CONTENT => 'No Content',
        ] as $statusCode => $description) {
            static::assertArrayHasKey($statusCode, $responses, \sprintf('Default response for status %d is missing', $statusCode));
            static::assertSame($description, $responses[$statusCode]->description);
        }
    }

    public function testEnrichUsesOAuthSecurityForAdminApi(): void
    {
        $openApi = new OpenApi([]);

        new OpenApiSchemaBuilder('6.7.0.0')->enrich($openApi, DefinitionService::API);

        static::assertSame([['oAuth' => ['write']]], $openApi->security);
        static::assertSame('Contena Admin API', $openApi->info->title);
    }

    public function testEnrichUsesApiKeySecurityForChannelApi(): void
    {
        $openApi = new OpenApi([]);

        new OpenApiSchemaBuilder('6.8.0.0')->enrich($openApi, DefinitionService::CHANNEL_API);

        static::assertSame([['ApiKey' => []]], $openApi->security);
        static::assertSame('Contena Channel API', $openApi->info->title);
    }

    public function testEnrichAddsRelationshipSchemasForAdminApi(): void
    {
        $openApi = new OpenApi([]);

        new OpenApiSchemaBuilder('6.8.0.0')->enrich($openApi, DefinitionService::API);

        $schema = json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'];

        static::assertSame(
            ['$ref' => '#/components/schemas/relationship'],
            $schema['relationships']['additionalProperties']
        );
        static::assertEqualsCanonicalizing(['data', 'meta', 'links'], array_keys($schema['relationship']['properties']));
        static::assertSame(1, $schema['relationship']['minProperties']);
        static::assertFalse($schema['relationship']['additionalProperties']);
        static::assertArrayNotHasKey('anyOf', $schema['relationship']);
    }

    public function testEnrichDoesNotAddAdminRelationshipSchemasForChannelApi(): void
    {
        $openApi = new OpenApi([]);

        new OpenApiSchemaBuilder('6.8.0.0')->enrich($openApi, DefinitionService::CHANNEL_API);

        $schemas = json_decode($openApi->toJson(), true, flags: \JSON_THROW_ON_ERROR)['components']['schemas'] ?? [];

        foreach (['relationships', 'relationship', 'relationshipToOne', 'relationshipToMany'] as $schemaName) {
            static::assertArrayNotHasKey($schemaName, $schemas);
        }
    }

    /**
     * @return array<int, OpenApiResponse>
     */
    private function getResponsesByStatusCode(OpenApi $openApi): array
    {
        static::assertInstanceOf(Components::class, $openApi->components);
        static::assertIsArray($openApi->components->responses);

        $responses = [];
        foreach ($openApi->components->responses as $response) {
            static::assertIsInt($response->response);
            $responses[$response->response] = $response;
        }

        return $responses;
    }
}
