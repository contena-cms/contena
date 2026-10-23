<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Validation\Constraint\NoHtml;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validation\UserProfileValidationFactory;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(UserProfileValidationFactory::class)]
class UserProfileValidationFactoryTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $accountTypes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountTypes = [UserEntity::ACCOUNT_TYPE_BUSINESS, UserEntity::ACCOUNT_TYPE_PRIVATE];
    }

    public function testCreateWithChannelContext(): void
    {
        $userProfileValidationFactory = new UserProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->create($channelContext);
        $expected = new DataValidationDefinition('user.profile.create');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->create($channelContext);
        $expected = new DataValidationDefinition('user.profile.create');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->create($channelContext);
        $expected = new DataValidationDefinition('user.profile.create');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testCreateWithChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->create($channelContext);
        $expected = new DataValidationDefinition('user.profile.create');
        $this->addConstraintsChannelContext($expected, $channelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithChannelContext(): void
    {
        $userProfileValidationFactory = new UserProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->update($channelContext);
        $expected = new DataValidationDefinition('user.profile.update');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithChannelContextButBirthdayFieldIsHidden(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => ['core.loginRegistration.showBirthdayField' => false],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->update($channelContext);
        $expected = new DataValidationDefinition('user.profile.update');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithChannelContextButBirthdayFieldIsOptional(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => false,
            ],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->update($channelContext);
        $expected = new DataValidationDefinition('user.profile.update');
        $this->addConstraintsChannelContext($expected, $channelContext);

        static::assertEquals($expected, $actual);
    }

    public function testUpdateWithChannelContextButBirthdayFieldIsRequired(): void
    {
        $configService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
            ],
        ]);

        $userProfileValidationFactory = new UserProfileValidationFactory(
            $configService,
            $this->accountTypes,
        );

        $channelContext = $this->getChannelContext();
        $actual = $userProfileValidationFactory->update($channelContext);
        $expected = new DataValidationDefinition('user.profile.update');
        $this->addConstraintsChannelContext($expected, $channelContext);
        $this->addConstraintsBirthday($expected);

        static::assertEquals($expected, $actual);
    }

    public function testNameFieldsRejectHtml(): void
    {
        $userProfileValidationFactory = new UserProfileValidationFactory(
            static::createStub(SystemConfigService::class),
            $this->accountTypes,
        );

        $properties = $userProfileValidationFactory->create($this->getChannelContext())->getProperties();

        foreach (['title', 'firstName', 'lastName'] as $field) {
            static::assertArrayHasKey($field, $properties);

            $noHtml = array_values(array_filter($properties[$field], static fn ($constraint) => $constraint instanceof NoHtml));

            static::assertCount(1, $noHtml, \sprintf('Expected exactly one NoHtml constraint on "%s".', $field));
            static::assertSame('VIOLATION::CONTAINS_HTML_ERROR', $noHtml[0]->getMessage());
        }
    }

    private function getChannelContext(): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        return Generator::generateChannelContext(
            channel: $channel,
        );
    }

    private function addConstraintsChannelContext(DataValidationDefinition $definition, ChannelContext $context): void
    {
        $definition
            ->add('salutationId', new EntityExists(entity: SalutationDefinition::ENTITY_NAME, context: $context->getContext()))
            ->add('firstName', new NotBlank())
            ->add('lastName', new NotBlank())
            ->add('accountType', new Choice(choices: $this->accountTypes))
            ->add('title', new Length(max: UserDefinition::MAX_LENGTH_TITLE))
            ->add('firstName', new Length(max: UserDefinition::MAX_LENGTH_FIRST_NAME))
            ->add('lastName', new Length(max: UserDefinition::MAX_LENGTH_LAST_NAME))
            ->add('title', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('firstName', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('lastName', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'));
    }

    private function addConstraintsBirthday(DataValidationDefinition $definition): void
    {
        $definition
            ->add('birthdayDay', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
            ->add('birthdayMonth', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
            ->add('birthdayYear', new NotBlank(), new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
    }
}
