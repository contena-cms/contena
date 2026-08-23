<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the insert-element mutation action.
 *
 * @internal
 */
final class InsertElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $type,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?int $index = null,
        public readonly ?string $rootSource = null,
        public readonly ?string $bindingSpecificationId = null,
    ) {
    }
}
