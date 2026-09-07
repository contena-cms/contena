<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Codec;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;

/**
 * Both directions of the stored forest's wire shape: the list of root element arrays a layout column holds,
 * and the {@see StoredTree} over them.
 *
 * It owns wire-level concerns only — the top level must be a list, and each entry must be an element shape.
 * Per-element work belongs to {@see StoredElementCodec}, including the nesting-depth guard, and every
 * tree-global invariant belongs to {@see StoredTree::validate()}: a forest that repeats an id decodes here
 * without complaint and is reported there.
 *
 * @internal
 */
final class StoredTreeCodec
{
    public function __construct(
        private readonly StoredElementCodec $elementCodec,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function decode(array $data): StoredTree
    {
        if (!array_is_list($data)) {
            throw ContentSystemException::invalidFieldValueType('layout', 'list of elements', 'associative array');
        }

        $roots = [];
        foreach ($data as $index => $element) {
            if (!\is_array($element)) {
                throw ContentSystemException::invalidFieldValueType(
                    \sprintf('layout[%d]', $index),
                    'array',
                    get_debug_type($element)
                );
            }

            $roots[] = $this->elementCodec->decode($element);
        }

        return new StoredTree($roots);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function encode(StoredTree $tree): array
    {
        return array_map(
            fn (StoredElement $root): array => $this->elementCodec->encode($root),
            $tree->roots
        );
    }
}
