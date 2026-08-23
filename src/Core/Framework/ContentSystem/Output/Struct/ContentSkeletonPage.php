<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * Layout metadata with element trees before hydration.
 *
 * @final
 */
class ContentSkeletonPage extends Struct
{
    /**
     * @param list<ContentSkeletonElement> $elements
     */
    public function __construct(
        public string $layoutId,
        public array $elements,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_skeleton_page';
    }
}
