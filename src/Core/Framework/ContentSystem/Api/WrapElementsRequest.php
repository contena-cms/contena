<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envelope DTO for the wrap-elements mutation action.
 *
 * @internal
 */
final class WrapElementsRequest
{
    /**
     * @param list<string> $elementIds
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        #[Assert\Type('array')]
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public readonly array $elementIds,
        public readonly string $containerType,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $slot = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}
