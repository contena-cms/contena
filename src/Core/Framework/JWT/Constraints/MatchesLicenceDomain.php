<?php declare(strict_types=1);

namespace Contena\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint;
use Contena\Core\Framework\JWT\JWTException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\InAppPurchase\Services\DecodedPurchasesCollectionStruct;
use Contena\Core\Framework\Store\Services\StoreService;
use Contena\Core\System\SystemConfig\SystemConfigService;

final readonly class MatchesLicenceDomain implements Constraint
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function assert(Token $token): void
    {
        $domain = $this->systemConfigService->get(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN);

        if (!$domain) {
            throw JWTException::missingDomain();
        }

        if (!$token instanceof UnencryptedToken) {
            throw JWTException::invalidJwt('Incorrect token type');
        }

        $purchases = DecodedPurchasesCollectionStruct::fromArray($token->claims()->all());

        $firstPurchase = $purchases->first();
        if (!$firstPurchase) {
            throw JWTException::invalidJwt('No purchases found in JWT');
        }

        if ($firstPurchase->sub !== $domain) {
            throw JWTException::invalidDomain($firstPurchase->sub);
        }
    }
}
