<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
final class BindElementRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $bindingSpecificationId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $rootSource = null,
    ) {
    }
}
