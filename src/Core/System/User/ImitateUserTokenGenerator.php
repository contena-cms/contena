<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\JWT\Channel\JWTGenerator;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\User\Struct\ImitateUserToken;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @extends JWTGenerator<ImitateUserToken>
 */
class ImitateUserTokenGenerator extends JWTGenerator
{
    public const HMAC_HASH_ALGORITHM = 'sha256';
    public const OPENSSL_CIPHER_ALGORITHM = 'aes-256-cbc';
    public const TOKEN_LIFETIME = 3600;

    /**
     * @internal
     */
    public function __construct(
        private readonly string $appSecret,
        private readonly Configuration $configuration,
        private readonly DataValidator $validator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($this->configuration, $this->validator);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `encode` method instead
     */
    public function generate(string $channelId, string $userId, string $adminUserId): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(ImitateUserTokenGenerator::class, 'generate', 'v6.8.0.0', 'parse'));

        $tokenData = [
            'channelId' => $channelId,
            'userId' => $userId,
            'admin_userId' => $adminUserId,
        ];

        $data = json_encode($tokenData);

        if ($data === false) {
            throw UserException::invalidImitationToken($channelId . ':' . $userId . ':' . $adminUserId);
        }

        return $this->encrypt(hash_hmac(self::HMAC_HASH_ALGORITHM, $data, $this->appSecret) . '.' . $this->clock->now()->getTimestamp());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use `decode` method instead
     */
    public function validate(string $givenToken, string $channelId, string $userId, string $adminUserId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(ImitateUserTokenGenerator::class, 'validate', 'v6.8.0.0', 'parse'));

        $tokenData = $this->decrypt($givenToken);

        $tokenData = explode('.', $tokenData);

        if (\count($tokenData) !== 2) {
            throw UserException::invalidImitationToken($givenToken);
        }

        $hash = $tokenData[0];
        $timeDiff = $this->clock->now()->getTimestamp() - (int) $tokenData[1];

        if ($timeDiff > self::TOKEN_LIFETIME) {
            throw UserException::invalidImitationToken($givenToken);
        }

        $givenTokenData = [
            'channelId' => $channelId,
            'userId' => $userId,
            'admin_userId' => $adminUserId,
        ];

        $data = json_encode($givenTokenData);

        if ($data === false) {
            throw UserException::invalidImitationToken($givenToken);
        }

        $expectedHash = hash_hmac(self::HMAC_HASH_ALGORITHM, $data, $this->appSecret);

        if (!hash_equals($hash, $expectedHash)) {
            throw UserException::invalidImitationToken($givenToken);
        }
    }

    protected function getJWTStructClass(): string
    {
        return ImitateUserToken::class;
    }

    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = parent::getStructConstraints();
        $definition->add(RegisteredClaims::ISSUER, new NotBlank(), new NotNull());

        return $definition;
    }

    private function encrypt(string $token): string
    {
        $iv = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(self::OPENSSL_CIPHER_ALGORITHM));
        $encrypted = openssl_encrypt($token, self::OPENSSL_CIPHER_ALGORITHM, $this->appSecret, 0, $iv);

        if ($encrypted === false) {
            throw UserException::invalidImitationToken($token);
        }

        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $token): string
    {
        $data = base64_decode($token, true);

        if ($data === false) {
            throw UserException::invalidImitationToken($token);
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        if (\strlen($iv) !== 16) {
            throw UserException::invalidImitationToken($token);
        }

        $decrypted = openssl_decrypt($encrypted, self::OPENSSL_CIPHER_ALGORITHM, $this->appSecret, 0, $iv);

        if ($decrypted === false) {
            throw UserException::invalidImitationToken($token);
        }

        return $decrypted;
    }
}
