<?php declare(strict_types=1);

namespace Contena\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Migration\Traits\StateMachineMigration;
use Contena\Core\Migration\Traits\StateMachineMigrationTrait;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789983699AddProcessTransitionFromUnconfirmed extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1789983699;
    }

    public function update(Connection $connection): void
    {
        $this->import(
            new StateMachineMigration(
                OrderTransactionStates::STATE_MACHINE,
                'Zahlungsstatus',
                'Payment state',
                [],
                [
                    StateMachineMigration::transition(
                        StateMachineTransitionActions::ACTION_PROCESS,
                        OrderTransactionStates::STATE_UNCONFIRMED,
                        OrderTransactionStates::STATE_IN_PROGRESS,
                    ),
                ],
            ),
            $connection
        );
    }
}
