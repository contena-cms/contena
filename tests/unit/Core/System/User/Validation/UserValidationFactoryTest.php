<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Validation\UserProfileValidationFactory;
use Contena\Core\System\User\Validation\UserValidationFactory;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(UserValidationFactory::class)]
class UserValidationFactoryTest extends TestCase
{
    #[DataProvider('getCreateTestData')]
    public function testCreate(
        DataValidationDefinition $profileDefinition,
        DataValidationDefinition $expected
    ): void {
        $userProfileValidationFactory = static::createStub(UserProfileValidationFactory::class);
        $userProfileValidationFactory
            ->method('create')
            ->willReturn($profileDefinition);

        $userValidationFactory = new UserValidationFactory($userProfileValidationFactory);
        $context = static::createStub(ChannelContext::class);

        $actual = $userValidationFactory->create($context);

        static::assertEquals($expected, $actual);
    }

    public static function getCreateTestData(): \Generator
    {
        $faker = Factory::create();

        // test with no constraints added
        $profileDefinition = new DataValidationDefinition();
        $expected = new DataValidationDefinition('user.create');
        self::addConstraints($expected);

        yield [$profileDefinition, $expected];

        // test merge
        $profileDefinition->add('email', new Type('string'));
        $expected->set('email', new Type('string'), new NotBlank(), new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));

        yield [$profileDefinition, $expected];

        // test with randomized data
        for ($i = 0; $i < 10; ++$i) {
            $profileDefinition = new DataValidationDefinition();

            $notBlankName = $faker->name();
            $profileDefinition->add($notBlankName, new NotBlank(null, 'VIOLATION::FIRST_NAME_IS_BLANK_ERROR'));

            $emailName = $faker->name();
            $profileDefinition->add($emailName, new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));

            $expected = new DataValidationDefinition('user.create');

            $expected->add($notBlankName, new NotBlank(null, 'VIOLATION::FIRST_NAME_IS_BLANK_ERROR'));
            $expected->add($emailName, new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));

            self::addConstraints($expected);

            yield [$profileDefinition, $expected];
        }
    }

    /**
     * @see UserValidationFactory::addConstraints
     */
    private static function addConstraints(DataValidationDefinition $definition): void
    {
        $definition->add('email', new NotBlank(), new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));
        $definition->add('active', new Type('boolean'));
    }
}
