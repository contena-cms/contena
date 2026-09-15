<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ScheduledTask;

use Contena\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CleanupCookieConsentLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'cookie_consent_log.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    /**
     * Nothing to clean up while no decisions are recorded
     */
    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return $bag->get('contena.cookie_consent.log_storage') !== NullCookieConsentLogStorage::NAME;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
