<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataContext;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;

/**
 * Resolves provider/consumer data flow with hierarchical context scoping.
 *
 * @internal
 *
 * @final
 */
class DataContextResolver
{
    public function __construct(
        private readonly ContextPathResolver $pathResolver
    ) {
    }

    public function resolve(ContentElement $element): void
    {
        $visitor = new ContextResolutionVisitor($this->pathResolver);
        $element->traverse($visitor);
    }
}
