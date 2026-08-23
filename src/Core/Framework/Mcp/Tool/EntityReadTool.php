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
    name: 'contena-entity-read',
    title: 'Entity Read',
    description: 'Read a single Contena entity by its UUID. Use when you already have an entity ID. For searching by other fields, use contena-entity-search instead. Returns {success, data: {id, ...fields}, _meta: {}}. Pass criteria JSON to include associations or select fields.'
)]
#[McpToolDependsOn('contena-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntityReadTool extends McpToolResponse
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

    public function __invoke(string $entity, string $id, string $criteria = '{}'): string
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

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria([$id]),
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
        $entityResult = $result->getEntities()->get($id);

        if ($entityResult === null) {
            return $this->error(\sprintf('Entity "%s" with ID "%s" not found.', $entity, $id));
        }

        $encoded = $this->encoder->encode($criteriaObj, $definition, $entityResult, '/api');

        return $this->success($encoded);
    }
}
