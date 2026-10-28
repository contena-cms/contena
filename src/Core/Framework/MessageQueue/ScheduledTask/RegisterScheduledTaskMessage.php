<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('framework')]
class RegisterScheduledTaskMessage implements AsyncMessageInterface
{
}
