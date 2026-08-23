<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

/**
 * Envelope DTO for the persisted replace-element mutation action. Carries the optimistic-concurrency
 * token (the layout's updatedAt, null for a never-updated layout); the layout id is a path parameter.
 *
 * @internal
 */
final class ContentLayoutReplaceRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $newType,
        public readonly ?string $expectedVersion,
    ) {
    }
}
