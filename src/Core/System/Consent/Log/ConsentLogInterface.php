<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Log;

use Contena\Core\System\Consent\ConsentStatus;

interface ConsentLogInterface
{
    public function log(ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void;
}
