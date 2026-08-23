<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as Hmac256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class JWTConfigurationFactory
{
    public static function createJWTConfiguration(): Configuration
    {
        /** @var non-empty-string $secret */
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET');
        $key = InMemory::plainText($secret);

        $configuration = Configuration::forSymmetricSigner(
            new Hmac256(),
            $key
        );

        $clock = new NativeClock(new \DateTimeZone(\date_default_timezone_get()));

        return $configuration->withValidationConstraints(
            new SignedWith(new Hmac256(), $key),
            new LooseValidAt($clock, null),
        );
    }
}
