<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the unwrap-element mutation action.
 *
 * @internal
 */
final class UnwrapElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $containerElementId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
