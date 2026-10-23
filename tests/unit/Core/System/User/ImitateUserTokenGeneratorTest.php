<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Exception\InvalidImitateUserTokenException;
use Contena\Core\System\User\ImitateUserTokenGenerator;
use Contena\Core\System\User\Struct\ImitateUserToken;
use Contena\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(ImitateUserTokenGenerator::class)]
class ImitateUserTokenGeneratorTest extends TestCase
{
    private const CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';
    private const CUSTOMER_ID = 'bcf76884cb764eb2b9650bb2fcf1073e';
    private const USER_ID = 'bcf76884cb764eb2b9650bb2fcf1073f';
    private const APP_SECRET = 'testAppSecret';

    private ImitateUserTokenGenerator $imitateUserTokenGenerator;

    private DataValidator&Stub $dataValidator;

    private Configuration $jwtConfiguration;

    protected function setUp(): void
    {
        $this->dataValidator = static::createStub(DataValidator::class);
        $this->jwtConfiguration = JWTConfigurationFactory::createJWTConfiguration();

        $this->imitateUserTokenGenerator = $this->createTokenGenerator();
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DoesNotPerformAssertions]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidate(): void
    {
        $token = $this->imitateUserTokenGenerator->generate(self::CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);

        $this->imitateUserTokenGenerator->validate($token, self::CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateWithInvalidToken(): void
    {
        $this->expectException(InvalidImitateUserTokenException::class);

        $this->imitateUserTokenGenerator->validate('invalidToken', self::CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed with tested method
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testValidateWithInvalidTimeToken(): void
    {
        $this->expectException(InvalidImitateUserTokenException::class);

        $token = $this->generate(self::CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID, time() - ImitateUserTokenGenerator::TOKEN_LIFETIME - 1);

        $this->imitateUserTokenGenerator->validate($token, self::CHANNEL_ID, self::CUSTOMER_ID, self::USER_ID);
    }

    public function testEncodeDecode(): void
    {
        $tokenStruct = new ImitateUserToken();
        $tokenStruct->channelId = self::CHANNEL_ID;
        $tokenStruct->userId = self::CUSTOMER_ID;
        $tokenStruct->iss = self::USER_ID;
        $token = $this->imitateUserTokenGenerator->encode($tokenStruct);

        $decodedToken = $this->imitateUserTokenGenerator->decode($token);

        static::assertSame(self::CHANNEL_ID, $decodedToken->channelId);
        static::assertSame(self::CUSTOMER_ID, $decodedToken->userId);
        static::assertSame(self::USER_ID, $decodedToken->iss);
    }

    public function testConstraint(): void
    {
        $tokenStruct = new ImitateUserToken();
        $token = $this->imitateUserTokenGenerator->encode($tokenStruct);

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('validate')
            ->with(static::isArray(), static::callback(static function (DataValidationDefinition $constraints): bool {
                $property = $constraints->getProperty('iss');
                static::assertEquals([new Type('string'), new NotBlank(), new NotNull()], $property);

                return true;
            }));

        $this->createTokenGenerator($dataValidator)->decode($token);
    }

    private function createTokenGenerator(?DataValidator $dataValidator = null): ImitateUserTokenGenerator
    {
        return new ImitateUserTokenGenerator(self::APP_SECRET, $this->jwtConfiguration, $dataValidator ?? $this->dataValidator, new NativeClock());
    }

    private function generate(string $channelId, string $userId, string $admin_userId, int $time): string
    {
        $tokenData = [
            'channelId' => $channelId,
            'userId' => $userId,
            'admin_userId' => $admin_userId,
        ];

        $data = json_encode($tokenData);

        if ($data === false) {
            throw UserException::invalidImitationToken($channelId . ':' . $userId . ':' . $admin_userId);
        }

        return $this->encrypt(hash_hmac(ImitateUserTokenGenerator::HMAC_HASH_ALGORITHM, $data, self::APP_SECRET) . '.' . $time);
    }

    private function encrypt(string $token): string
    {
        $iv = openssl_random_pseudo_bytes((int) openssl_cipher_iv_length(ImitateUserTokenGenerator::OPENSSL_CIPHER_ALGORITHM));
        $encrypted = openssl_encrypt($token, ImitateUserTokenGenerator::OPENSSL_CIPHER_ALGORITHM, self::APP_SECRET, 0, $iv);

        if ($encrypted === false) {
            throw UserException::invalidImitationToken($token);
        }

        return base64_encode($iv . $encrypted);
    }
}
