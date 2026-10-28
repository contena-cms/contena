<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Mutation;

use Contena\Core\Framework\Log\Package;

/**
 * The slot coordinates of a non-root element: the id of its parent and the slot it lives in.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ParentSlot
{
    public function __construct(
        public string $parentId,
        public string $slot,
    ) {
    }
}
