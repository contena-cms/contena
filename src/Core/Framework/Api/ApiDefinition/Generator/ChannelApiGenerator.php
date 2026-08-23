<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\License;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use Contena\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

/**
 * @internal
 *
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
class ChannelApiGenerator implements ApiDefinitionGeneratorInterface
{
    final public const FORMAT = 'openapi-3';
    private const OPERATION_KEYS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    private readonly string $schemaPath;

    /**
     * @param array{Framework: array{path: string}} $bundles
     *
     * @internal
     */
    public function __construct(
        private readonly OpenApiSchemaBuilder $openApiBuilder,
        private readonly OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder,
        array $bundles,
        private readonly BundleSchemaPathCollection $bundleSchemaPathCollection,
        private readonly ?OpenApiRouteDefaultsFilter $routeDefaultsFilter = null,
    ) {
        $this->schemaPath = $bundles['Framework']['path'] . '/Api/ApiDefinition/Generator/Schema/ChannelApi';
    }

    public function supports(string $format, string $api): bool
    {
        return $format === self::FORMAT && $api === DefinitionService::CHANNEL_API;
    }

    public function generate(array $definitions, string $api, string $apiType, ?string $bundleName): array
    {
        $openApi = new OpenApi([
            'openapi' => '3.2.0',
        ]);
        $this->openApiBuilder->enrich($openApi, $api);

        $forChannel = $api === DefinitionService::CHANNEL_API;

        ksort($definitions);

        $schemaPaths = [$this->schemaPath];

        if ($bundleName !== null && $bundleName !== '') {
            $schemaPaths = array_merge([$this->schemaPath . '/components', $this->schemaPath . '/tags'], $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
        } else {
            $schemaPaths = array_merge($schemaPaths, $this->bundleSchemaPathCollection->getSchemaPaths($api, $bundleName));
        }

        $loader = new OpenApiFileLoader($schemaPaths);
        $jsonSpec = $loader->loadOpenapiSpecification();
        $jsonSchemaNames = [];
        if (isset($jsonSpec['components']['schemas']) && \is_array($jsonSpec['components']['schemas'])) {
            foreach (array_keys($jsonSpec['components']['schemas']) as $schemaName) {
                if (\is_string($schemaName)) {
                    $jsonSchemaNames[] = $schemaName;
                }
            }
        }
        $generatedSchemas = $this->getGeneratedSchemas($definitions, $jsonSchemaNames, $forChannel);
        $referencedJsonSchemaNames = $this->getReferencedJsonSchemaNames($jsonSpec, $generatedSchemas['componentSchemas']);

        foreach ($generatedSchemas['definitionSchemas'] as $schemaName => $schema) {
            if (\in_array($schemaName, $jsonSchemaNames, true)) {
                // A matching JSON component owns the base schema; PHP contributes only dynamic extensions.
                $openApi->components->merge(array_values($schema));

                continue;
            }

            if (!array_intersect(array_keys($schema), $referencedJsonSchemaNames)) {
                continue;
            }

            $openApi->components->merge(array_values($schema));
        }

        $this->addGeneralInformation($openApi);
        $this->addContentTypeParameter($openApi);

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $data['paths'] ??= [];
        $data['components']['schemas'] ??= [];

        $preFinalSpecs = $this->mergeComponentsSchemaRequiredFieldsRecursive($data, $jsonSpec);
        /** @var OpenApiSpec $finalSpecs */
        $finalSpecs = array_replace_recursive($data, $preFinalSpecs);

        $this->filterUndefinedRequiredProperties($finalSpecs);
        /** @var OpenApiSpec $finalSpecs */
        $this->resolveParameterGroups($finalSpecs);
        $this->injectContextHeaders($finalSpecs);
        $this->enrichPathsWithAssociations($finalSpecs, $definitions);

        return $this->routeDefaultsFilter?->filter($finalSpecs, $api) ?? $finalSpecs;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, EntityDefinition>|array<string, EntityDefinition&ChannelDefinitionInterface> $definitions
     *
     * @return never
     */
    public function getSchema(array $definitions): array
    {
        throw ApiException::unsupportedChannelApiSchemaEndpoint();
    }

    private function shouldDefinitionBeIncluded(EntityDefinition $definition): bool
    {
        if (preg_match('/_translation$/', $definition->getEntityName())) {
            return false;
        }

        if (mb_strpos($definition->getEntityName(), 'version') === 0) {
            return false;
        }

        return true;
    }

    private function shouldIncludeReferenceOnly(EntityDefinition $definition, bool $forChannel): bool
    {
        $class = new \ReflectionClass($definition);
        if ($class->isSubclassOf(MappingEntityDefinition::class)) {
            return true;
        }

        if ($forChannel && !is_subclass_of($definition, ChannelDefinitionInterface::class)) {
            return true;
        }

        return false;
    }

    private function getResourceUri(EntityDefinition $definition, string $rootPath = '/'): string
    {
        return ltrim('/', $rootPath) . '/' . str_replace('_', '-', $definition->getEntityName());
    }

    private function addGeneralInformation(OpenApi $openApi): void
    {
        $openApi->info->description = 'This endpoint reference contains an overview of all endpoints comprising the Contena Channel API';
        $openApi->info->license = new License(['name' => 'MIT']);
    }

    private function addContentTypeParameter(OpenApi $openApi): void
    {
        $openApi->components->parameters = [
            new Parameter([
                'parameter' => 'contentType',
                'name' => 'Content-Type',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Content type of the request',
            ]),
            new Parameter([
                'parameter' => 'accept',
                'name' => 'Accept',
                'in' => 'header',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'default' => 'application/json',
                ],
                'description' => 'Accepted response content types',
            ]),
            new Parameter([
                'parameter' => 'swLanguageId',
                'name' => PlatformRequest::HEADER_LANGUAGE_ID,
                'in' => 'header',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'pattern' => '^[0-9a-f]{32}$',
                ],
                'description' => 'Instructs Contena to return the response in the given language.',
            ]),
            new Parameter([
                'parameter' => 'swDomain',
                'name' => PlatformRequest::HEADER_DOMAIN,
                'in' => 'header',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'example' => 'https://channel.example.com/de',
                ],
                'description' => 'URL of a configured channel domain. Headless frontends can use this header to have '
                    . 'the request served with the language configured for that domain, without knowing its ID. '
                    . 'Must match one of the channel\'s configured domains. Explicit `ct-language-id` '
                    . 'headers take precedence.',
            ]),
        ];

        if (!is_iterable($openApi->paths)) {
            return;
        }

        foreach ($openApi->paths as $path) {
            foreach (self::OPERATION_KEYS as $key) {
                $operation = $path->$key;

                if (!$operation instanceof Operation) {
                    continue;
                }

                if (!\is_array($operation->parameters)) {
                    $operation->parameters = [];
                }

                array_push(
                    $operation->parameters,
                    new Parameter(['ref' => '#/components/parameters/contentType']),
                    new Parameter(['ref' => '#/components/parameters/accept']),
                );
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $specsFromDefinition
     * @param array<string, array<string, mixed>> $specsFromStaticJsonDefinition
     *
     * @return array<string, array<string, mixed>>
     */
    private function mergeComponentsSchemaRequiredFieldsRecursive(array $specsFromDefinition, array $specsFromStaticJsonDefinition): array
    {
        foreach ($specsFromDefinition['components']['schemas'] ?? [] as $key => $value) {
            if (isset($specsFromStaticJsonDefinition['components']['schemas'][$key]['required']) && isset($specsFromDefinition['components']['schemas'][$key]['required'])) {
                $specsFromStaticJsonDefinition['components']['schemas'][$key]['required']
                    = array_merge_recursive(
                        $specsFromStaticJsonDefinition['components']['schemas'][$key]['required'],
                        $specsFromDefinition['components']['schemas'][$key]['required']
                    );
            }
        }

        return $specsFromStaticJsonDefinition;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function filterUndefinedRequiredProperties(array &$schema): void
    {
        foreach ($schema as &$value) {
            if (!\is_array($value)) {
                continue;
            }

            $this->filterUndefinedRequiredProperties($value);
        }

        if (!isset($schema['required'], $schema['properties']) || !\is_array($schema['required']) || !\is_array($schema['properties'])) {
            return;
        }

        $properties = array_flip(array_keys($schema['properties']));
        $schema['required'] = array_values(array_filter(
            $schema['required'],
            static fn (mixed $property): bool => \is_string($property) && isset($properties[$property])
        ));

        if ($schema['required'] === []) {
            unset($schema['required']);
        }
    }

    /**
     * @param array<string, EntityDefinition> $definitions
     * @param list<string> $jsonSchemaNames
     *
     * @return array{
     *     definitionSchemas: array<string, array<string, mixed>>,
     *     componentSchemas: array<string, mixed>
     * }
     */
    private function getGeneratedSchemas(array $definitions, array $jsonSchemaNames, bool $forChannel): array
    {
        $definitionSchemas = [];

        foreach ($definitions as $definition) {
            if (!$definition instanceof EntityDefinition) {
                continue;
            }

            if (!$this->shouldDefinitionBeIncluded($definition)) {
                continue;
            }

            $schemaName = $this->definitionSchemaBuilder->getSchemaName($definition);

            if (\in_array($schemaName, $jsonSchemaNames, true)) {
                $definitionSchemas[$schemaName] = $this->definitionSchemaBuilder->getExtensionSchemaByDefinition(
                    $definition,
                    $this->getResourceUri($definition),
                    $forChannel,
                );

                continue;
            }

            $definitionSchemas[$schemaName] = $this->definitionSchemaBuilder->getSchemaByDefinition(
                $definition,
                $this->getResourceUri($definition),
                $forChannel,
                $this->shouldIncludeReferenceOnly($definition, $forChannel),
                DefinitionService::TYPE_JSON_API,
            );
        }

        $openApi = new OpenApi([
            'openapi' => '3.2.0',
        ]);
        $openApi->components = new Components([]);

        foreach ($definitionSchemas as $schema) {
            $openApi->components->merge(array_values($schema));
        }

        $data = json_decode($openApi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $componentSchemas = $data['components']['schemas'] ?? [];
        if (!\is_array($componentSchemas)) {
            $componentSchemas = [];
        }

        return [
            'definitionSchemas' => $definitionSchemas,
            'componentSchemas' => $componentSchemas,
        ];
    }

    /**
     * @param array<string, mixed> $jsonSpec
     * @param array<string, mixed> $generatedComponentSchemas
     *
     * @return list<string>
     */
    private function getReferencedJsonSchemaNames(array $jsonSpec, array $generatedComponentSchemas): array
    {
        $componentSchemas = $jsonSpec['components']['schemas'] ?? [];
        if (!\is_array($componentSchemas)) {
            $componentSchemas = [];
        }
        $componentSchemas = array_replace_recursive($componentSchemas, $generatedComponentSchemas);

        $referencedSchemaNames = [];
        $queue = [];
        $this->collectSchemaReferences($jsonSpec['paths'] ?? [], $queue);

        while ($queue !== []) {
            $schemaName = array_shift($queue);
            if (isset($referencedSchemaNames[$schemaName]) || !\is_string($schemaName)) {
                continue;
            }

            $referencedSchemaNames[$schemaName] = true;

            if (isset($componentSchemas[$schemaName])) {
                $this->collectSchemaReferences($componentSchemas[$schemaName], $queue);
            }
        }

        return array_keys($referencedSchemaNames);
    }

    /**
     * @param list<string> $schemaNames
     */
    private function collectSchemaReferences(mixed $value, array &$schemaNames): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (isset($value['$ref']) && \is_string($value['$ref']) && str_starts_with($value['$ref'], '#/components/schemas/')) {
            $schemaNames[] = mb_substr($value['$ref'], 21);
        }

        foreach ($value as $nestedValue) {
            $this->collectSchemaReferences($nestedValue, $schemaNames);
        }
    }

    /**
     * [WARNING] Please refrain from using this functionality in new code. It is a workaround to reduce duplication of
     * the criteria parameter groups and may be removed in the future.
     *
     * OpenAPI specification does not support groups of parameters as reusable components.
     * As in Contena has a number of GET routes that support passing criteria as a set of parameters,
     * describing them in the OpenAPI spec leads to a lot of duplication.
     *
     * This methods adds support for a custom extension that allows describing parameter groups in the components
     * and referencing them in the separate paths as a group. Those groups will be resolved and replaced with the actual parameters.
     *
     * Example:
     *
     * ```json
     * {
     *   "components": {
     *     "x-parameter-groups": {
     *       "pagination": [
     *         {
     *           "name": "limit",
     *           "in": "query",
     *           "required": false,
     *            ... usual parameter properties
     *         },
     *         {
     *           "name": "page",
     *           ... usual parameter properties
     *         }
     *       ]
     *     }
     *   },
     *   "paths": {
     *     "/blog": {
     *       "get": {
     *         "parameters": [
     *           {
     *             "x-parameter-group": "pagination"
     *           },
     *           ... other parameters
     *         ]
     *         ... usual operation properties
     *       }
     *     }
     *   }
     * }
     * ```
     *
     * @param OpenApiSpec $specs
     */
    private function resolveParameterGroups(array &$specs): void
    {
        // this is a custom extension that is not supported by the OpenAPI spec
        // it has to be processed and removed before the final output
        $parameterGroups = $specs['components']['x-parameter-groups'] ?? [];
        unset($specs['components']['x-parameter-groups']);

        foreach ($specs['paths'] as &$pathDefinition) {
            foreach ($pathDefinition as &$operation) {
                if (!isset($operation['parameters']) || !\is_array($operation['parameters'])) {
                    continue;
                }

                $newParams = [];
                $hasGroup = false;

                foreach ($operation['parameters'] as $parameter) {
                    if (isset($parameter['x-parameter-group'])) {
                        $hasGroup = true;
                        $groupNames = (array) $parameter['x-parameter-group'];

                        foreach ($groupNames as $groupName) {
                            if (isset($parameterGroups[$groupName])) {
                                array_push($newParams, ...$parameterGroups[$groupName]);
                            }
                        }
                    } else {
                        $newParams[] = $parameter;
                    }
                }

                if ($hasGroup) {
                    $operation['parameters'] = $newParams;
                }
            }
        }
    }

    /**
     * Injects the language-related context headers (ct-language-id and ct-domain) into Channel API operations whose
     * responses can surface translated content. Both headers select the response language; ct-domain derives it from
     * a configured channel domain for headless clients. DELETE operations are skipped because they only confirm
     * removal and do not return localised payloads, and tooling endpoints under /_info/* are skipped because they serve
     * schema and routing metadata. The HTTP-method filter is portable across third-party plugins and apps that
     * contribute their own Channel API endpoints. Operations that already declare a header (by name or $ref) are left
     * untouched so bundle-provided schemas with an explicit declaration are never duplicated.
     *
     * @param OpenApiSpec $specs
     */
    private function injectContextHeaders(array &$specs): void
    {
        $headers = [
            ['name' => PlatformRequest::HEADER_LANGUAGE_ID, 'ref' => '#/components/parameters/swLanguageId'],
            ['name' => PlatformRequest::HEADER_DOMAIN, 'ref' => '#/components/parameters/swDomain'],
        ];

        foreach ($specs['paths'] as $path => &$pathDefinition) {
            if (str_starts_with((string) $path, '/_info/')) {
                continue;
            }

            foreach (self::OPERATION_KEYS as $method) {
                if ($method === 'delete') {
                    continue;
                }

                if (!isset($pathDefinition[$method])) {
                    continue;
                }

                if (!\is_array($pathDefinition[$method]['parameters'] ?? null)) {
                    $pathDefinition[$method]['parameters'] = [];
                }

                foreach ($headers as $header) {
                    $alreadyDeclared = false;
                    foreach ($pathDefinition[$method]['parameters'] as $param) {
                        if (
                            (isset($param['name']) && strtolower((string) $param['name']) === $header['name'])
                            || (isset($param['$ref']) && $param['$ref'] === $header['ref'])
                        ) {
                            $alreadyDeclared = true;

                            break;
                        }
                    }

                    if (!$alreadyDeclared) {
                        $pathDefinition[$method]['parameters'][] = ['$ref' => $header['ref']];
                    }
                }
            }
        }
    }

    /**
     * Automatically enriches path descriptions with available associations
     *
     * @param OpenApiSpec $specs
     * @param array<string, EntityDefinition> $definitions
     */
    private function enrichPathsWithAssociations(array &$specs, array $definitions): void
    {
        // Build a map of entity names to their association documentation
        $associationDocs = [];
        foreach ($definitions as $def) {
            if (!$def instanceof EntityDefinition) {
                continue;
            }

            $doc = $this->getAssociationsDocumentation($def);
            if ($doc !== '') {
                $associationDocs[$def->getEntityName()] = $doc;
            }
        }

        // Enrich all paths
        foreach ($specs['paths'] as &$pathDefinition) {
            foreach (self::OPERATION_KEYS as $method) {
                if (!isset($pathDefinition[$method])) {
                    continue;
                }

                // Only enrich read operations (operationId starts with "read")
                if (!isset($pathDefinition[$method]['operationId'])
                    || !str_starts_with($pathDefinition[$method]['operationId'], 'read')) {
                    continue;
                }

                // Try to find entity reference in the response schema
                $entityName = $this->extractEntityNameFromOperation($pathDefinition[$method]);

                if (!$entityName || !isset($associationDocs[$entityName])) {
                    continue;
                }

                // Append associations documentation
                if (isset($pathDefinition[$method]['description'])) {
                    $currentDesc = $pathDefinition[$method]['description'];
                    // Only add if not already present
                    if (!str_contains($currentDesc, '**Available Associations:**')) {
                        $pathDefinition[$method]['description'] = $currentDesc . $associationDocs[$entityName];
                    }
                }
            }
        }
    }

    /**
     * Extracts entity name from operation response schemas
     *
     * @param array<string, mixed> $operation
     */
    private function extractEntityNameFromOperation(array $operation): ?string
    {
        // Handle response-level $ref (e.g., "$ref": "#/components/responses/BlogListResponse")
        if (isset($operation['responses']['200']['$ref'])) {
            $ref = $operation['responses']['200']['$ref'];
            // Extract entity name from response reference like "BlogListResponse" -> "blog"
            // Match pattern: components/responses/{Entity}[List|Detail]Response
            if (\is_string($ref) && preg_match('#/([^/]+?)(?:List|Detail)?Response$#', $ref, $matches)) {
                $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]);
                if (!\is_string($converted)) {
                    return null;
                }

                return strtolower($converted);
            }
        }

        // Check if there's a 200 response with a schema
        if (!isset($operation['responses']['200']['content']['application/json']['schema'])) {
            return null;
        }

        $schema = $operation['responses']['200']['content']['application/json']['schema'];

        // Check for direct reference (e.g., "#/components/schemas/MemberGroup" or "BlogDetailResponse")
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            // Check if it's a RouteResponse wrapper - extract actual entity reference
            if (str_contains($ref, 'RouteResponse')) {
                return $this->extractEntityFromRouteResponseRef($ref);
            }
            // Check if it's a DetailResponse wrapper (BlogDetailResponse -> blog)
            if (str_contains($ref, 'DetailResponse')) {
                return $this->extractEntityFromDetailResponseRef($ref);
            }
            // Check if it's a Result wrapper (BlogListingResult, etc.)
            if (str_contains($ref, 'Result')) {
                $entityName = $this->extractEntityFromResultRef($ref);
                if ($entityName) {
                    return $entityName;
                }
            }

            return $this->extractEntityNameFromRef($ref);
        }

        // Check for allOf with references
        if (isset($schema['allOf']) && \is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $item) {
                if (isset($item['$ref'])) {
                    $ref = $item['$ref'];
                    if (str_contains($ref, 'RouteResponse')) {
                        $entityName = $this->extractEntityFromRouteResponseRef($ref);
                    } elseif (str_contains($ref, 'DetailResponse')) {
                        $entityName = $this->extractEntityFromDetailResponseRef($ref);
                    } elseif (str_contains($ref, 'Result')) {
                        $entityName = $this->extractEntityFromResultRef($ref);
                    } else {
                        $entityName = $this->extractEntityNameFromRef($ref);
                    }
                    if ($entityName) {
                        return $entityName;
                    }
                }
            }
        }

        // Check for array items reference (collection endpoints)
        if (isset($schema['properties']['elements']['items']['$ref'])) {
            return $this->extractEntityNameFromRef($schema['properties']['elements']['items']['$ref']);
        }

        return null;
    }

    /**
     * Extracts entity name from Result schema reference
     * Example: "#/components/schemas/BlogListingResult" -> "blog"
     *
     * This handles wrapper classes like BlogListingResult, EntitySearchResult, etc.
     */
    private function extractEntityFromResultRef(string $ref): ?string
    {
        // Common patterns:
        // BlogListingResult -> blog
        // EntitySearchResult -> generic, skip

        // Extract schema name from reference
        if (!preg_match('#/([^/]+)Result$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Skip generic result wrappers
        if (\in_array($schemaName, ['EntitySearch', 'Search'], true)) {
            return null;
        }

        // Handle patterns like "BlogListing" -> "blog"
        // Remove common suffixes before converting
        $schemaName = preg_replace('/(?:Listing|Search|Collection)$/', '', $schemaName);
        if (!\is_string($schemaName)) {
            return null;
        }

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from RouteResponse schema reference
     * Example: "#/components/schemas/MemberRouteResponse" -> "member"
     */
    private function extractEntityFromRouteResponseRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)RouteResponse$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from DetailResponse schema reference
     * Example: "#/components/schemas/BlogDetailResponse" -> "blog"
     */
    private function extractEntityFromDetailResponseRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)DetailResponse$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Extracts entity name from schema reference
     * Example: "#/components/schemas/MemberGroup" -> "member_group"
     */
    private function extractEntityNameFromRef(string $ref): ?string
    {
        // Extract schema name from reference
        if (!preg_match('#/([^/]+)$#', $ref, $matches)) {
            return null;
        }

        $schemaName = $matches[1];

        // Convert PascalCase to snake_case
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $schemaName);
        if (!\is_string($converted)) {
            return null;
        }

        return strtolower($converted);
    }

    /**
     * Generates documentation for available associations
     */
    private function getAssociationsDocumentation(EntityDefinition $definition): string
    {
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if (!$field instanceof AssociationField) {
                continue;
            }

            // Skip if explicitly hidden from OpenAPI
            if ($field->getFlag(IgnoreInOpenapiSchema::class)) {
                continue;
            }

            // Skip translations
            if ($field->getPropertyName() === 'translations') {
                continue;
            }

            // Skip parent associations - they cannot be loaded via Criteria due to infinite recursion prevention
            // Error: FRAMEWORK__PARENT_ASSOCIATION_CAN_NOT_BE_FETCHED
            if ($field instanceof ParentAssociationField) {
                continue;
            }

            // Check ApiAware flag for Channel API
            $apiAware = $field->getFlag(ApiAware::class);
            if (!$apiAware || !$apiAware->isSourceAllowed(ChannelApiSource::class)) {
                continue;
            }

            $fieldName = $field->getPropertyName();

            // Get description from Field
            $description = $field->getDescription();

            // Build the association line
            $line = '- `' . $fieldName . '`';

            if ($description) {
                $line .= ' - ' . $description;
            }

            $associations[] = $line;
        }

        if ($associations === []) {
            return '';
        }

        return "\n\n**Available Associations:**\n" . implode("\n", $associations);
    }
}
