<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
final class InsertPresetRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $presetId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}
