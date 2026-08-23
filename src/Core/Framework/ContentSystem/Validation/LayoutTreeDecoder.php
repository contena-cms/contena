<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Validation;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementListField;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementListFieldSerializer;

/**
 * Single decode path from a content_layout `layout` storage value to its element tree, used by the
 * {@see ContentLayoutWriteValidator} so the decode step has one definition. Throws on any decode defect; the
 * validator records a client-defect decode as an invalid_config violation and rethrows any other.
 *
 * @internal
 *
 * @final
 */
class LayoutTreeDecoder
{
    public function __construct(
        private readonly ContentLayoutDefinition $definition,
        private readonly ContentElementListFieldSerializer $listSerializer,
    ) {
    }

    /**
     * @throws ContentSystemException
     *
     * @return list<ContentElement>
     */
    public function decode(mixed $value): array
    {
        $field = $this->definition->getField(ContentLayoutDefinition::LAYOUT_FIELD);
        \assert($field instanceof ContentElementListField);

        return array_values($this->listSerializer->decode($field, $value) ?? []);
    }
}
