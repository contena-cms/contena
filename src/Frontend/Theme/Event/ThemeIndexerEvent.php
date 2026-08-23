<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;

class ThemeIndexerEvent extends NestedEvent
{
    /**
     * @param array<string> $ids
     * @param array<string> $skip
     */
    public function __construct(
        private readonly array $ids,
        private readonly Context $context,
        private readonly array $skip = []
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @return array<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
