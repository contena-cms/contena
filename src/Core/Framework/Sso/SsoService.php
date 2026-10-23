<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso;

use Contena\Core\Framework\Api\OAuth\AuthCodeRepository;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\Config\LoginConfig;
use Contena\Core\Framework\Sso\Config\LoginConfigService;

/**
 * @internal
 */
class SsoService
{
    public function __construct(
        private readonly LoginConfigService $loginConfigService,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepository $authCodeRepository,
    ) {
    }

    public function isSso(): bool
    {
        return $this->loginConfigService->getConfig() instanceof LoginConfig;
    }

    public function revokeUserTokens(string $admin_userId): void
    {
        $this->refreshTokenRepository->revokeRefreshTokensForUser($admin_userId);
        $this->authCodeRepository->revokeAuthCodesForUser($admin_userId);
    }
}
