<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;

abstract class FlowAction
{
    /**
     * @return list<class-string>
     */
    abstract public function requirements(): array;

    abstract public function handleFlow(StorableFlow $flow): void;

    abstract public static function getName(): string;
}
