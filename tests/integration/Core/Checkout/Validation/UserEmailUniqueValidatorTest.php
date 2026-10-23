<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Validation;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserEmailUniqueValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function tearDown(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->delete('core.systemWideLoginRegistration.isUserBoundToChannel');

        parent::tearDown();
    }

    public function testSameUserEmailWithExistingBoundAccountOnDifferentChannel(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);

        $email = 'john.doe@example.com';

        $this->createUserOfChannel(TestDefaults::CHANNEL, $email);
        $constraint = $this->createConstraint(Uuid::randomHex());

        $validation = new DataValidationDefinition('user.email.update');
        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);
        $violations = [];
        try {
            $validator->validate(['email' => $email], $validation);
        } catch (ConstraintViolationException $exception) {
            $violations = $exception->getViolations();
        }
        static::assertCount(0, $violations, 'No violations are expected');
    }

    public function testSameUserEmailOnSameChannel(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);

        $email = 'john.doe@example.com';

        $this->createUserOfChannel(TestDefaults::CHANNEL, $email);
        $constraint = $this->createConstraint(TestDefaults::CHANNEL);

        $validation = new DataValidationDefinition('user.email.update');

        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);

        try {
            $validator->validate([
                'email' => $email,
            ], $validation);

            static::fail('No exception is thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);

            static::assertSame($constraint->getMessage(), $violation->getMessageTemplate());
        }
    }

    public function testSameUserEmailWithExistingNonBoundAccount(): void
    {
        static::getContainer()
            ->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);

        $email = 'john.doe@example.com';

        $this->createUserOfChannel(TestDefaults::CHANNEL, $email, false);

        $constraint = $this->createConstraint(TestDefaults::CHANNEL);

        $validation = new DataValidationDefinition('user.email.update');

        $validation->add('email', $constraint);

        $validator = static::getContainer()->get(DataValidator::class);

        try {
            $validator->validate([
                'email' => $email,
            ], $validation);

            static::fail('No exception is thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);

            static::assertSame($constraint->getMessage(), $violation->getMessageTemplate());
        }
    }

    private function createUserOfChannel(string $channelId, string $email, bool $boundToChannel = true): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'boundChannelId' => $boundToChannel ? $channelId : null,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => $channelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], Context::createDefaultContext());

        return $userId;
    }

    private function createConstraint(string $channelId): UserEmailUnique
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')
            ->willReturn($channelId);

        return new UserEmailUnique(channelContext: $channelContext);
    }
}
