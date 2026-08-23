<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @template IDStructure of string|array<string, string> = string
 *
 * @extends EntityWrittenEvent<IDStructure>
 */
class EntityDeletedEvent extends EntityWrittenEvent
{
    /**
     * @param list<EntityWriteResult<IDStructure>> $writeResult
     * @param array<mixed> $errors
     */
    public function __construct(
        string $entityName,
        array $writeResult,
        Context $context,
        array $errors = []
    ) {
        parent::__construct($entityName, $writeResult, $context, $errors);

        $this->name = $entityName . '.deleted';
    }
}
