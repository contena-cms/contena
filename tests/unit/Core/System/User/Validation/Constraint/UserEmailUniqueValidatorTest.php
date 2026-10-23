<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\System\User\Validation\Constraint\UserEmailUniqueValidator;
use Contena\Core\System\User\Validation\UserEmailUniqueCheck;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<UserEmailUniqueValidator>
 */
#[CoversClass(UserEmailUniqueValidator::class)]
class UserEmailUniqueValidatorTest extends ConstraintValidatorTestCase
{
    private UserEmailUniqueChecker&Stub $userEmailUniqueChecker;

    protected function setUp(): void
    {
        $this->userEmailUniqueChecker = static::createStub(UserEmailUniqueChecker::class);

        parent::setUp();
    }

    public function testItIgnoresNullValue(): void
    {
        $checker = $this->createMock(UserEmailUniqueChecker::class);
        $checker->expects($this->never())
            ->method('isUnique');

        $validator = new UserEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate(null, $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItIgnoresEmptyString(): void
    {
        $checker = $this->createMock(UserEmailUniqueChecker::class);
        $checker->expects($this->never())
            ->method('isUnique');

        $validator = new UserEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate('', $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItPassesEmailAndChannelScopeToChecker(): void
    {
        $email = 'user@example.com';
        $channelId = Uuid::randomHex();

        $checker = $this->createMock(UserEmailUniqueChecker::class);
        $checker->expects($this->once())
            ->method('isUnique')
            ->with(static::callback(static function (UserEmailUniqueCheck $check) use ($email, $channelId): bool {
                static::assertSame($email, $check->email);
                static::assertNull($check->userId);
                static::assertSame($channelId, $check->boundChannelId);
                static::assertFalse($check->guest);

                return true;
            }))
            ->willReturn(true);

        $validator = new UserEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate($email, $this->createConstraint($channelId));

        $this->assertNoViolation();
    }

    public function testItBuildsViolationWhenEmailIsNotUnique(): void
    {
        $email = 'user@example.com';
        $this->setValue($email);

        $checker = $this->createMock(UserEmailUniqueChecker::class);
        $checker->expects($this->once())
            ->method('isUnique')
            ->willReturn(false);

        $validator = new UserEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $constraint = $this->createConstraint();

        $validator->validate($email, $constraint);

        $this->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', '"' . $email . '"')
            ->setInvalidValue($email)
            ->setCode(UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->assertRaised();
    }

    public function testItRejectsUnexpectedConstraintType(): void
    {
        try {
            $this->validator->validate('user@example.com', new NotBlank());

            static::fail('Expected unexpected constraint type exception.');
        } catch (\Throwable $exception) {
            static::assertTrue(
                $exception instanceof UserException || $exception instanceof UnexpectedTypeException,
                \sprintf('Expected user unexpected type exception, got %s.', $exception::class)
            );
        }
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new UserEmailUniqueValidator($this->userEmailUniqueChecker);
    }

    private function createConstraint(?string $channelId = null): UserEmailUnique
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')
            ->willReturn($channelId ?? Uuid::randomHex());

        return new UserEmailUnique(channelContext: $channelContext);
    }
}
