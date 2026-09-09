<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Api\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal only for use by the app-system
 */
class VerifyInstallation
{
    public function __construct(
        #[Assert\NotBlank]
        public string $runId,
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
