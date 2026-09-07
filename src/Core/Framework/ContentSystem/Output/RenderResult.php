<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Output;

use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;

/**
 * What one render produced: the finished rendered forest, the layout it came from, and — when the response
 * format asks for it — the {@see ResolvedValueIndex} over that forest's property values.
 *
 * The index is nullable because it is optional output, not because it may be missing: a format that serves
 * property values inline needs no index, and skeleton rendering has no property values to index at all.
 */
final readonly class RenderResult
{
    /**
     * @param list<RenderedElement> $tree
     */
    public function __construct(
        public array $tree,
        public LayoutReference $reference,
        public ?ResolvedValueIndex $index,
    ) {
    }
}
