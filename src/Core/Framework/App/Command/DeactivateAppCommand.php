<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Command;

use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal only for use by the app-system
 */
#[AsCommand(
    name: 'app:deactivate',
    description: 'Deactivates an app',
)]
class DeactivateAppCommand extends AbstractAppActivationCommand
{
    private const ACTION = 'deactivate';

    public function __construct(
        AppStorage $appStorage,
        private readonly AbstractAppLifecycle $appLifecycle
    ) {
        parent::__construct($appStorage, self::ACTION);
    }

    public function runAction(string $appId, Context $context): void
    {
        $this->appLifecycle->deactivate($appId, $context);
    }
}
