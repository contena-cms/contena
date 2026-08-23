<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;

#[McpTool(
    name: 'contena-entity-search',
    title: 'Entity Search',
    description: 'Search and filter Contena entities with Admin API criteria JSON. For count, sum, or average reporting use contena-entity-aggregate; this tool returns records and pagination metadata. Use contena-entity-schema first when field names are unknown.'
)]
#[McpToolDependsOn('contena-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntitySearchTool extends McpToolResponse
{
    use McpEntityIncludes;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
        private readonly JsonEntityEncoder $encoder,
        private readonly AclCriteriaValidator $criteriaValidator,
    ) {
    }

    public function __invoke(string $entity, string $criteria = '{}', int $limit = 25, int $page = 1, string $term = ''): string
    {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the contena://entities resource for available entity names.', $entity));
        }

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
        }

        $payload = $this->decodeJsonOrError($criteria, 'criteria');
        if (\is_string($payload)) {
            return $payload;
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $payload['limit'] ??= $limit;
        $payload['total-count-mode'] ??= Criteria::TOTAL_COUNT_MODE_EXACT;
        if ($page > 1) {
            $payload['page'] = $page;
        }
        if ($term !== '') {
            $payload['term'] = $term;
        }

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $context,
        );

        // Criteria can reference associated entities that require their own read privileges
        // (same association ACL model as the Admin API).
        $missing = $this->criteriaValidator->validate($entity, $criteriaObj, $context);
        if ($missing !== []) {
            return $this->missingPrivilegesError($missing);
        }

        $this->applyDefaultIncludes($definition, $criteriaObj);

        $result = $repository->search($criteriaObj, $context);

        $limit = $criteriaObj->getLimit() ?? 25;

        $encoded = $this->encoder->encode($criteriaObj, $definition, $result->getEntities(), '/api');

        return $this->success($encoded, [
            'total' => $result->getTotal(),
            'page' => $criteriaObj->getOffset() ? (int) ($criteriaObj->getOffset() / $limit) + 1 : 1,
            'limit' => $limit,
        ]);
    }
}
