<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

/**
 * @internal
 */
final class ContentLayoutBindRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $bindingSpecificationId,
        public readonly ?string $expectedVersion,
    ) {
    }
}
