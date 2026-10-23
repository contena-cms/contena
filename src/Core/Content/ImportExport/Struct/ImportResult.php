<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Struct;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

/**
 * @internal
 */
class ImportResult
{
    /**
     * @param EntityWrittenContainerEvent[] $results
     * @param array<int, array<string, mixed>> $failedRecords
     */
    public function __construct(public readonly array $results, public readonly array $failedRecords)
    {
    }
}
