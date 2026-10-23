<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @deprecated tag:v6.8.0 - Will be removed as it was not dispatched anymore, call TaskRegistry synchronously
 */
class RegisterScheduledTaskMessage implements AsyncMessageInterface
{
}
