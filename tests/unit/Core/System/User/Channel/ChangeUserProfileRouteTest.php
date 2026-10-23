<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Channel\ChangeUserProfileRoute;
use Contena\Core\System\User\Extension\ChangeUserProfileRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentification;
use Contena\Core\System\User\Validation\UserValidationFactory;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ChangeUserProfileRoute::class)]
class ChangeUserProfileRouteTest extends TestCase
{
    public function testVatIdsAreNormalizedBeforeValidation(): void
    {
        $countryId = Uuid::randomHex();
        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setCheckVatIdPattern(true);

        $billingAddress = new UserAddressEntity();
        $billingAddress->setCountryId($countryId);
        $billingAddress->setCountry($country);

        $user = new UserEntity();
        $user->setId('user1');
        $user->setDefaultBillingAddress($billingAddress);

        $validationFactory = static::createStub(UserValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn(new DataValidationDefinition());

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data['vatIds']);

                    return true;
                }),
                static::isInstanceOf(DataValidationDefinition::class)
            );

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data[0]['vatIds']);

                    return true;
                }),
                static::isInstanceOf(Context::class)
            );

        $change = new ChangeUserProfileRoute(
            $userRepository,
            new EventDispatcher(),
            $validator,
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => Uuid::randomHex(),
            'vatIds' => ['de 123456789'],
        ]);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $change->change($data, $channelContext, $user);
    }

    public function testVatIdsAreValidatedAgainstTheActiveBillingCountry(): void
    {
        $defaultCountry = new CountryEntity();
        $defaultCountry->setId(Uuid::randomHex());
        $defaultCountry->setVatIdRequired(false);

        $defaultBillingAddress = new UserAddressEntity();
        $defaultBillingAddress->setCountryId($defaultCountry->getId());
        $defaultBillingAddress->setCountry($defaultCountry);

        $activeCountry = new CountryEntity();
        $activeCountry->setId(Uuid::randomHex());
        $activeCountry->setVatIdRequired(true);

        $activeBillingAddress = new UserAddressEntity();
        $activeBillingAddress->setCountryId($activeCountry->getId());
        $activeBillingAddress->setCountry($activeCountry);

        $user = new UserEntity();
        $user->setId('user1');
        $user->setDefaultBillingAddress($defaultBillingAddress);
        $user->setActiveBillingAddress($activeBillingAddress);

        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(UserValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn($validation);

        $change = new ChangeUserProfileRoute(
            static::createStub(EntityRepository::class),
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'salutationId' => Uuid::randomHex(),
        ]);

        $change->change($data, static::createStub(ChannelContext::class), $user);

        $vatIdConstraints = $validation->getProperty('vatIds');

        $vatIdentification = array_values(array_filter($vatIdConstraints, static fn (Constraint $constraint) => $constraint instanceof UserVatIdentification))[0] ?? null;
        static::assertInstanceOf(UserVatIdentification::class, $vatIdentification);
        static::assertSame($activeCountry->getId(), $vatIdentification->getCountryId());
        static::assertContainsEquals(new NotBlank(), $vatIdConstraints);
    }

    public function testCustomFieldsGetPassed(): void
    {
        $customFields = new RequestDataBag(['test1' => '1', 'test2' => '2']);

        $userRepository = static::createStub(EntityRepository::class);

        $storeApiCustomFieldMapper = $this->createMock(StoreApiCustomFieldMapper::class);
        $storeApiCustomFieldMapper
            ->expects($this->once())
            ->method('map')
            ->with('user', $customFields)
            ->willReturn(['test1' => '1']);

        $change = new ChangeUserProfileRoute(
            $userRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(UserValidationFactory::class),
            $storeApiCustomFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $user = new UserEntity();
        $user->setId('user1');
        $user->setAccountType(UserEntity::ACCOUNT_TYPE_PRIVATE);
        $data = new RequestDataBag([
            'customFields' => $customFields,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(ChannelContext::class), $user);
    }

    public function testAccountTypeGetPassed(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertArrayHasKey('accountType', $data[0]);

                return true;
            }));

        $change = new ChangeUserProfileRoute(
            $userRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(UserValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $user = new UserEntity();
        $user->setId('user1');
        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(ChannelContext::class), $user);
    }

    public function testBusinessUserKeepsCompanyAndVatIdsWhenTheRequestOmitsThem(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessUser(), [
            'name' => 'Jane',
            'lastName' => 'Doe',
        ]);

        static::assertSame('Jane', $written['firstName']);
        static::assertArrayNotHasKey('accountType', $written);
        static::assertArrayNotHasKey('company', $written);
        static::assertArrayNotHasKey('vatIds', $written);
        static::assertSame([], $validation->getProperty('company'));
        static::assertSame([], $validation->getProperty('vatIds'));
    }

    public function testBusinessUserChangesCompanyAndVatIdsSentWithoutAccountType(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessUser(), [
            'name' => 'Jane',
            'lastName' => 'Doe',
            'company' => 'New Company',
            'vatIds' => ['DE987654321'],
        ]);

        static::assertArrayNotHasKey('accountType', $written);
        static::assertSame('New Company', $written['company']);
        static::assertSame(['DE987654321'], $written['vatIds']);
        static::assertContainsEquals(new NotBlank(), $validation->getProperty('company'));
        static::assertContainsEquals(new NotBlank(), $validation->getProperty('vatIds'));
    }

    public function testPrivateUserWithoutAccountTypeGetsNoCompany(): void
    {
        $user = $this->createBusinessUser();
        $user->setAccountType(UserEntity::ACCOUNT_TYPE_PRIVATE);

        [$written] = $this->changeProfile($user, [
            'name' => 'Jane',
            'lastName' => 'Doe',
            'company' => 'New Company',
        ]);

        static::assertSame('', $written['company']);
        static::assertNull($written['vatIds']);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = static::createStub(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $change = new ChangeUserProfileRoute(
            $userRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(UserValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            $salutationRepository,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $user = new UserEntity();
        $user->setId('user1');

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '',
        ]);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn(TestDefaults::CHANNEL);

        $change->change($data, $channelContext, $user);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-user-profile-route.change.pre', static function (ChangeUserProfileRouteExtension $extension) use ($data, $context, $user, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangeUserProfileRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(UserValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($data, $context, $user));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{0: array<string, mixed>, 1: DataValidationDefinition}
     */
    private function changeProfile(UserEntity $user, array $request): array
    {
        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(UserValidationFactory::class);
        $validationFactory->method('update')->willReturn($validation);

        $written = [];
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $route = new ChangeUserProfileRoute(
            $userRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->change(new RequestDataBag(['salutationId' => Uuid::randomHex(), ...$request]), static::createStub(ChannelContext::class), $user);

        return [$written, $validation];
    }

    private function createBusinessUser(): UserEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setVatIdRequired(true);

        $billingAddress = new UserAddressEntity();
        $billingAddress->setCountryId($country->getId());
        $billingAddress->setCountry($country);

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setAccountType(UserEntity::ACCOUNT_TYPE_BUSINESS);
        $user->setCompany('Example Inc.');
        $user->setVatIds(['DE123456789']);
        $user->setActiveBillingAddress($billingAddress);

        return $user;
    }
}
