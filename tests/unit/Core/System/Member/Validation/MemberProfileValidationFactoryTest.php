<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\Validation\MemberProfileValidationFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(MemberProfileValidationFactory::class)]
class MemberProfileValidationFactoryTest extends TestCase
{
    #[DataProvider('profileValidationCases')]
    public function testProfileValidation(bool $update, bool $showBirthday, bool $birthdayRequired): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showBirthdayField' => $showBirthday,
                'core.loginRegistration.birthdayFieldRequired' => $birthdayRequired,
            ],
        ]);

        $factory = new MemberProfileValidationFactory($configService);
        $channelContext = Generator::generateChannelContext();

        $actual = $update ? $factory->update($channelContext) : $factory->create($channelContext);
        $expected = new DataValidationDefinition($update ? 'member.profile.update' : 'member.profile.create');
        $this->addProfileConstraints($expected);

        if ($showBirthday && $birthdayRequired) {
            $this->addBirthdayConstraints($expected);
        }

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithDefaultChannelConfiguration(): void
    {
        $factory = new MemberProfileValidationFactory(static::createStub(SystemConfigService::class));

        $actual = $factory->create(Generator::generateChannelContext());
        $expected = new DataValidationDefinition('member.profile.create');
        $this->addProfileConstraints($expected);

        static::assertEquals($expected, $actual);
    }

    /**
     * @return \Generator<string, array{bool, bool, bool}>
     */
    public static function profileValidationCases(): \Generator
    {
        yield 'create with hidden birthday' => [false, false, false];
        yield 'create with optional birthday' => [false, true, false];
        yield 'create with required birthday' => [false, true, true];
        yield 'update with hidden birthday' => [true, false, false];
        yield 'update with optional birthday' => [true, true, false];
        yield 'update with required birthday' => [true, true, true];
    }

    private function addProfileConstraints(DataValidationDefinition $definition): void
    {
        $definition
            ->add('title', new Length(max: MemberDefinition::MAX_LENGTH_TITLE))
            ->add('name', new NotBlank(), new Length(max: MemberDefinition::MAX_LENGTH_NAME))
            ->add('phoneNumber', new Length(max: MemberDefinition::MAX_LENGTH_PHONE_NUMBER));
    }

    private function addBirthdayConstraints(DataValidationDefinition $definition): void
    {
        $definition
            ->add('birthdayDay', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
            ->add('birthdayMonth', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
            ->add('birthdayYear', new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
    }
}
