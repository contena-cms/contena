<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write;

use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @internal use entity repository to write data
 */
interface EntityWriterInterface
{
    /**
     * @param list<SyncOperation> $operations
     */
    public function sync(array $operations, WriteContext $context): WriteResult;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, list<EntityWriteResult>>
     */
    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array;

    /**
     * @param array<array<string, string>> $rawData
     */
    public function delete(EntityDefinition $definition, array $rawData, WriteContext $writeContext): WriteResult;
}
