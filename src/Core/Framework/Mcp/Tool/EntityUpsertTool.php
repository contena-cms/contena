<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;

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

    public function __invoke(string $entity, string $payload, bool $dryRun = true): string
    {
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
