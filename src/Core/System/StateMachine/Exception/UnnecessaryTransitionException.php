<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Exception;

use Contena\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UnnecessaryTransitionException extends StateMachineException
{
    public function __construct(string $transition)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::UNNECESSARY_TRANSITION,
            'The transition "{{ transition }}" is unnecessary, already on desired state.',
            ['transition' => $transition]
        );
    }
}
