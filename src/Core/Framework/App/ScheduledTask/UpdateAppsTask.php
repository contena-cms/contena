<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ScheduledTask;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UpdateAppsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app_update';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return $bag->get('contena.deployment.runtime_extension_management');
    }
}
