<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Log;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\System\Consent\ConsentStatus;

/**
 * @internal
 */
class DatabaseLog implements ConsentLogInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function log(ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void
    {
        $logEntry = [
            'consent-name' => $consentName,
            'action' => $action->value,
            'identifier' => $identifier,
            'actor' => $actor,
        ];

        $this->connection->insert('consent_log', [
            'consent_name' => $consentName,
            'timestamp' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'message' => \json_encode($logEntry),
        ]);
    }
}
