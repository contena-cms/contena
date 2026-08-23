<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the move-element mutation action.
 *
 * @internal
 */
final class MoveElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $newParentId = null,
        public readonly ?string $newSlot = null,
        public readonly ?int $index = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}
