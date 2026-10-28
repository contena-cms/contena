<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutBindRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $bindingSpecificationId,
        public readonly ?string $expectedVersion,
    ) {
    }
}
