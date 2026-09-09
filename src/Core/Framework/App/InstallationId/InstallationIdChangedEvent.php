<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\AppException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class InstallationIdChangedEvent extends Event
{
    public function __construct(
        public readonly InstallationId $newInstallationId,
        public readonly ?InstallationId $oldInstallationId
    ) {
        if ($oldInstallationId !== null && $oldInstallationId->id === $newInstallationId->id) {
            throw AppException::invalidArgument('InstallationIdChangedEvent requires a changed installation id');
        }
    }
}
