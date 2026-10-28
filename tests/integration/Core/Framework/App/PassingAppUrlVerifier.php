<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Contena\Core\Framework\App\Url\VerificationState;
use Contena\Core\Framework\App\Url\VerificationStatus;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class PassingAppUrlVerifier extends AppUrlVerifier
{
    public function __construct()
    {
    }

    public function forceVerify(): VerificationState
    {
        return new VerificationState((string) EnvironmentHelper::getVariable('APP_URL'), VerificationStatus::PASS, 1, new \DateTimeImmutable(), info: null, statusCode: null);
    }

    public function verifyForAdministration(): bool
    {
        return true;
    }
}
