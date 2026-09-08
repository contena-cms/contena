<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(
    name: 'contena-entity-upsert',
    title: 'Entity Upsert',
    description: 'Create or update Contena entity data. Always use dryRun=true (default) first to validate, then set dryRun=false to persist. Use contena-entity-schema to understand required fields before building the payload. Returns validation result in dryRun mode, or the written entity data on commit.'
)]
#[McpToolDependsOn('contena-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['create', 'update'])]
class EntityUpsertTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'Entity name to write, e.g. "blog" or "category". See the contena://entities resource for the full list.')]
        string $entity,
        #[Schema(description: 'The entity\'s fields as a JSON string: one OBJECT, or an ARRAY of objects to write several records in a single call. Include "id" on a record to UPDATE it, omit it to CREATE one — e.g. {"id":"...","name":"News"} renames an existing category, and [{...},{...}] upserts both. contena-entity-schema lists the field names and which are required.')]
        string $payload,
        #[Schema(description: 'Validate without writing. Leave true first, then call again with false to persist.')]
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the contena://entities resource for available entity names.', $entity));
        }

        $data = $this->decodeJsonOrError($payload, 'payload');
        if (\is_string($data)) {
            return $data;
        }

        if (!\array_is_list($data)) {
            $data = [$data];
        }

        $needsCreate = false;
        $needsUpdate = false;
        foreach ($data as $item) {
            if (isset($item['id'])) {
                $needsUpdate = true;
            } else {
                $needsCreate = true;
            }
        }

        $privileges = [];
        if ($needsCreate) {
            $privileges[] = $entity . ':create';
        }
        if ($needsUpdate) {
            $privileges[] = $entity . ':update';
        }
        if ($privileges === []) {
            $privileges[] = $entity . ':create';
        }

        if ($error = $this->requirePrivilege($context, ...$privileges)) {
            return $error;
        }

        $repository = $this->registry->getRepository($entity);

        if ($dryRun) {
            return $this->executeWithDryRun($this->connection, $context, function () use ($repository, $data, $context) {
                $events = $repository->upsert($data, $context);

                return $this->success($this->formatWriteEvents($events, 'upsert'), ['dryRun' => true]);
            });
        }

        $events = $repository->upsert($data, $context);

        return $this->success($this->formatWriteEvents($events, 'upsert'), ['dryRun' => false]);
    }
}
