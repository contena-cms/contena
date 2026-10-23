<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'sales-channel:maintenance:disable',
    description: 'Disable maintenance mode for a sales channel',
)]
class ChannelMaintenanceDisableCommand extends ChannelMaintenanceEnableCommand
{
    protected bool $setMaintenanceMode = false;
}
