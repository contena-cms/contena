<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal not intended for decoration or replacement
 */
class Sequence extends Struct
{
    public string $flowId;

    public string $sequenceId;

    public static function createIF(
        string $ruleId,
        string $flowId,
        string $sequenceId,
        ?Sequence $true,
        ?Sequence $false
    ): IfSequence {
        $sequence = new IfSequence();
        $sequence->ruleId = $ruleId;
        $sequence->trueCase = $true;
        $sequence->falseCase = $false;
        $sequence->flowId = $flowId;
        $sequence->sequenceId = $sequenceId;

        return $sequence;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createAction(
        string $action,
        ?Sequence $nextAction,
        string $flowId,
        string $sequenceId,
        array $config = []
    ): ActionSequence {
        $sequence = new ActionSequence();
        $sequence->action = $action;
        $sequence->config = $config;
        $sequence->nextAction = $nextAction;
        $sequence->flowId = $flowId;
        $sequence->sequenceId = $sequenceId;

        return $sequence;
    }
}
