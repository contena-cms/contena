<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Visitor;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;

/**
 * @internal
 */
interface ElementVisitor
{
    public function enter(ContentElement $element): void;

    public function leave(ContentElement $element): void;
}
