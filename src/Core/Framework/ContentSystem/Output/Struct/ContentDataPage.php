<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * Layout metadata with deduplicated property data and element-to-data mappings.
 *
 * @final
 */
class ContentDataPage extends Struct
{
    /**
     * @param array<string, mixed> $data Deduplicated property values (refId => value)
     * @param array<string, array<string, string>> $assignments Element-to-property mappings (elementId => [propKey => refId])
     */
    public function __construct(
        public string $layoutId,
        public array $data,
        public array $assignments,
        public string $layoutName,
        public ?string $layoutVersion,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'content_data_page';
    }
}
