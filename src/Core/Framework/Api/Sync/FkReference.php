<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Sync;

/**
 * @final
 */
class FkReference
{
    public ?string $resolved = null;

    /**
     * @internal
     */
    public function __construct(
        public readonly string $pointer,
        public readonly string $entityName,
        public readonly string $fieldName,
        public mixed $value,
        public readonly bool $nullOnMissing
    ) {
    }
}
