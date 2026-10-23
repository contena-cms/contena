<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Struct;

use Contena\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
class IfSequence extends Sequence
{
    public string $ruleId;

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;
}
