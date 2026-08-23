<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUnique;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUniqueValidator;
use Contena\Core\System\Member\Validation\MemberEmailUniqueCheck;
use Contena\Core\System\Member\Validation\MemberEmailUniqueChecker;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<MemberEmailUniqueValidator>
 */
#[CoversClass(MemberEmailUniqueValidator::class)]
class MemberEmailUniqueValidatorTest extends ConstraintValidatorTestCase
{
    private MemberEmailUniqueChecker&Stub $memberEmailUniqueChecker;

    protected function setUp(): void
    {
        $this->memberEmailUniqueChecker = static::createStub(MemberEmailUniqueChecker::class);

        parent::setUp();
    }

    public function testItIgnoresNullValue(): void
    {
        $checker = $this->createMock(MemberEmailUniqueChecker::class);
        $checker->expects($this->never())
            ->method('isUnique');

        $validator = new MemberEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate(null, $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItIgnoresEmptyString(): void
    {
        $checker = $this->createMock(MemberEmailUniqueChecker::class);
        $checker->expects($this->never())
            ->method('isUnique');

        $validator = new MemberEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate('', $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItPassesEmailAndChannelScopeToChecker(): void
    {
        $email = 'member@example.com';
        $channelId = Uuid::randomHex();

        $checker = $this->createMock(MemberEmailUniqueChecker::class);
        $checker->expects($this->once())
            ->method('isUnique')
            ->with(static::callback(static function (MemberEmailUniqueCheck $check) use ($email, $channelId): bool {
                static::assertSame($email, $check->email);
                static::assertNull($check->memberId);
                static::assertSame($channelId, $check->channelId);

                return true;
            }))
            ->willReturn(true);

        $validator = new MemberEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $validator->validate($email, $this->createConstraint($channelId));

        $this->assertNoViolation();
    }

    public function testItBuildsViolationWhenEmailIsNotUnique(): void
    {
        $email = 'member@example.com';
        $this->setValue($email);

        $checker = $this->createMock(MemberEmailUniqueChecker::class);
        $checker->expects($this->once())
            ->method('isUnique')
            ->willReturn(false);

        $validator = new MemberEmailUniqueValidator($checker);
        $validator->initialize($this->context);

        $constraint = $this->createConstraint();

        $validator->validate($email, $constraint);

        $this->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', '"' . $email . '"')
            ->setInvalidValue($email)
            ->setCode(MemberEmailUnique::MEMBER_EMAIL_NOT_UNIQUE)
            ->assertRaised();
    }

    public function testItRejectsUnexpectedConstraintType(): void
    {
        $this->expectException(MemberException::class);

        $this->validator->validate('member@example.com', new NotBlank());
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new MemberEmailUniqueValidator($this->memberEmailUniqueChecker);
    }

    private function createConstraint(?string $channelId = null): MemberEmailUnique
    {
        $channel = new ChannelEntity();
        $channel->setId($channelId ?? Uuid::randomHex());

        return new MemberEmailUnique(channelContext: Generator::generateChannelContext(channel: $channel));
    }
}
