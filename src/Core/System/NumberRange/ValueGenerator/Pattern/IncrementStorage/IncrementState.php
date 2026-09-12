<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Contena\Core\Framework\Uuid\Uuid;

/**
 * Transport value for one increment state, including its ownership boundary.
 *
 * @internal
 */
final readonly class IncrementState
{
    public function __construct(
        public string $dataScopeId,
        public string $numberRangeId,
        public int $value,
    ) {
        if (!Uuid::isValid($dataScopeId)) {
            throw new \InvalidArgumentException('Increment state data scope id must be a valid UUID.');
        }

        if (!Uuid::isValid($numberRangeId)) {
            throw new \InvalidArgumentException('Increment state number range id must be a valid UUID.');
        }
    }
}
