<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Visitor\ElementVisitor;

/**
 * @final
 */
class OrderTrackingVisitor implements ElementVisitor
{
    /**
     * @var list<string>
     */
    public array $log = [];

    public function enter(ContentElement $element): void
    {
        $this->log[] = 'enter:' . $element->getComponent();
    }

    public function leave(ContentElement $element): void
    {
        $this->log[] = 'leave:' . $element->getComponent();
    }
}
