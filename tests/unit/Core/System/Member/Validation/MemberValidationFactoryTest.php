<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Member\Validation\MemberProfileValidationFactory;
use Contena\Core\System\Member\Validation\MemberValidationFactory;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(MemberValidationFactory::class)]
class MemberValidationFactoryTest extends TestCase
{
    #[DataProvider('getCreateTestData')]
    public function testCreate(
        DataValidationDefinition $profileDefinition,
        DataValidationDefinition $expected
    ): void {
        $memberProfileValidationFactory = static::createStub(MemberProfileValidationFactory::class);
        $memberProfileValidationFactory
            ->method('create')
            ->willReturn($profileDefinition);

        $memberValidationFactory = new MemberValidationFactory($memberProfileValidationFactory);

        $actual = $memberValidationFactory->create(Generator::generateChannelContext());

        static::assertEquals($expected, $actual);
    }

    public static function getCreateTestData(): \Generator
    {
        $profileDefinition = new DataValidationDefinition();
        $expected = new DataValidationDefinition('member.create');
        self::addConstraints($expected);

        yield 'adds member constraints to an empty profile definition' => [$profileDefinition, $expected];

        $profileDefinition->add('email', new Type('string'));
        $expected->set('email', new Type('string'), new NotBlank(), new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));

        yield 'merges member constraints with existing profile constraints' => [$profileDefinition, $expected];

        $profileDefinition = new DataValidationDefinition();
        $profileDefinition->add('displayName', new NotBlank(null, 'VIOLATION::FIRST_NAME_IS_BLANK_ERROR'));
        $profileDefinition->add('contactEmail', new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));

        $expected = new DataValidationDefinition('member.create');
        $expected->add('displayName', new NotBlank(null, 'VIOLATION::FIRST_NAME_IS_BLANK_ERROR'));
        $expected->add('contactEmail', new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));
        self::addConstraints($expected);

        yield 'merges unrelated profile constraints' => [$profileDefinition, $expected];
    }

    /**
     * @see MemberValidationFactory::addConstraints
     */
    private static function addConstraints(DataValidationDefinition $definition): void
    {
        $definition->add('email', new NotBlank(), new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'));
        $definition->add('active', new Type('boolean'));
    }
}
