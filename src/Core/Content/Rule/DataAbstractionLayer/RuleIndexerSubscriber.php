<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Rule\RuleEvents;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class RuleIndexerSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'refreshPlugin',
            PluginPostActivateEvent::class => 'refreshPlugin',
            PluginPostUpdateEvent::class => 'refreshPlugin',
            PluginPostDeactivateEvent::class => 'refreshPlugin',
            PluginPostUninstallEvent::class => 'refreshPlugin',
            RuleEvents::RULE_WRITTEN_EVENT => 'onRuleWritten',
        ];
    }

    public function refreshPlugin(): void
    {
        // Delete the payload and invalid flag of all rules
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET `payload` = null, `invalid` = 0, `updated_at` = :updatedAt')
        );
        $update->execute([
            'updatedAt' => $now,
        ]);
    }

    public function onRuleWritten(): void
    {
        $this->cartRuleLoader->invalidate();
    }
}
