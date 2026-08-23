<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

/**
 * Envelope DTO for the persisted move-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
final class ContentLayoutMoveRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly ?string $expectedVersion,
        public readonly ?string $newParentId = null,
        public readonly ?string $newSlot = null,
        public readonly ?int $index = null,
    ) {
    }
}
