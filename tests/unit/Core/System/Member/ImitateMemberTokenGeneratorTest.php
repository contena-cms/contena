<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member;

use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Member\ImitateMemberTokenGenerator;
use Contena\Core\System\Member\Struct\ImitateMemberToken;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(ImitateMemberTokenGenerator::class)]
class ImitateMemberTokenGeneratorTest extends TestCase
{
    private const string CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';
    private const string MEMBER_ID = 'bcf76884cb764eb2b9650bb2fcf1073e';
    private const string USER_ID = 'bcf76884cb764eb2b9650bb2fcf1073f';

    private ImitateMemberTokenGenerator $imitateMemberTokenGenerator;

    private DataValidator&Stub $dataValidator;

    private Configuration $jwtConfiguration;

    protected function setUp(): void
    {
        $this->dataValidator = static::createStub(DataValidator::class);
        $this->jwtConfiguration = JWTConfigurationFactory::createJWTConfiguration();

        $this->imitateMemberTokenGenerator = $this->createTokenGenerator();
    }

    public function testEncodeDecode(): void
    {
        $tokenStruct = new ImitateMemberToken();
        $tokenStruct->channelId = self::CHANNEL_ID;
        $tokenStruct->memberId = self::MEMBER_ID;
        $tokenStruct->iss = self::USER_ID;
        $token = $this->imitateMemberTokenGenerator->encode($tokenStruct);

        $decodedToken = $this->imitateMemberTokenGenerator->decode($token);

        static::assertSame(self::CHANNEL_ID, $decodedToken->channelId);
        static::assertSame(self::MEMBER_ID, $decodedToken->memberId);
        static::assertSame(self::USER_ID, $decodedToken->iss);
    }

    public function testConstraint(): void
    {
        $tokenStruct = new ImitateMemberToken();
        $token = $this->imitateMemberTokenGenerator->encode($tokenStruct);

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

    private function createTokenGenerator(?DataValidator $dataValidator = null): ImitateMemberTokenGenerator
    {
        return new ImitateMemberTokenGenerator($this->jwtConfiguration, $dataValidator ?? $this->dataValidator);
    }
}
