<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Struct;

/**
 * @internal not intended for decoration or replacement
 */
class ActionSequence extends Sequence
{
    public string $action;

    /**
     * @var array<string, mixed>
     */
    public array $config = [];

    public ?Sequence $nextAction = null;
}
