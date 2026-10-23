<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class AccountProfileControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testDeleteUserProfile(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $browser = $this->login($user->getEmail());

        $browser->request('POST', $_SERVER['APP_URL'] . '/account/profile/delete');

        $response = $browser->getResponse();

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
    }

    public function testAccountOverviewPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $browser = $this->login($user->getEmail());

        $browser->request('GET', '/account');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('account-overview-page-loaded', $traces);
    }

    public function testAccountProfilePageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $browser = $this->login($user->getEmail());

        $browser->request('GET', '/account/profile');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('account-profile-page-loaded', $traces);
    }

    public function testPrivateAccountTypeNotForcedToCommercialWithCompanySignupEnabled(): void
    {
        $context = Context::createDefaultContext();

        // Create a user with private account type
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $userGroupId = Uuid::randomHex();

        // Create a user group with company signup form enabled
        $userGroupRepository = static::getContainer()->get('user_group.repository');
        $userGroupRepository->create([
            [
                'id' => $userGroupId,
                'name' => 'Test Group with Company Signup',
                'registrationActive' => true,
                'registrationTitle' => 'Company Registration',
                'registrationOnlyCompanyRegistration' => true, // This enables the company signup form
            ],
        ], $context);

        // Create a private user
        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE, // Explicitly set as private
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'John',
                'lastName' => 'Doe',
                'street' => 'Test Street 1',
                'city' => 'Test City',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'Germany'],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => $userGroupId, // Assign to the group with company signup enabled
            'email' => 'private.user@test.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'John',
            'lastName' => 'Doe',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '54321',
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');
        $repo->create([$user], $context);

        // Enable the account type selection in system config
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.showAccountTypeSelection', true);

        // Login as the private user
        $browser = $this->login($user['email']);

        // Request the profile page
        $browser->request('GET', '/account/profile');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Check that the page content shows the correct account type
        $content = $response->getContent();
        static::assertNotFalse($content);

        // The account type field should be present and not forcibly set to commercial
        static::assertStringContainsString('name="accountType"', $content);

        // Check that private account option is available and selected
        static::assertStringContainsString('value="' . UserEntity::ACCOUNT_TYPE_PRIVATE . '"', $content);

        // Verify that the profile can be saved without requiring company fields
        $browser->request(
            'POST',
            '/account/profile',
            $this->tokenize('frontend.account.profile.save', [
                'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
                'salutationId' => $this->getValidSalutationId(),
                'name' => 'John',
                'lastName' => 'Doe',
                'email' => 'private.user@test.com',
            ])
        );

        $response = $browser->getResponse();
        static::assertTrue($response->isRedirect(), 'Response should redirect after saving profile. Response code: ' . $response->getStatusCode());

        // Verify the user is still private type in the database
        $updatedUser = $repo->search(new Criteria([$userId]), $context)->getEntities()->first();
        static::assertNotNull($updatedUser);
        static::assertSame(UserEntity::ACCOUNT_TYPE_PRIVATE, $updatedUser->getAccountType());
    }

    public function testVatIdFieldIsRequiredOnProfileWhenBillingCountryRequiresVatId(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createBusinessUser($context, true);

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.showAccountTypeSelection', true);

        $browser = $this->login($user->getEmail());

        $crawler = $browser->request('GET', '/account/profile');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $vatIdField = $crawler->filter('#vatIds');
        static::assertCount(1, $vatIdField);
        static::assertStringContainsString('required', (string) $vatIdField->attr('data-validation'));
        static::assertCount(1, $crawler->filter('label[for="vatIds"] .form-required-label'));
    }

    public function testVatIdFieldIsOptionalOnProfileWhenBillingCountryDoesNotRequireVatId(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createBusinessUser($context, false);

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.showAccountTypeSelection', true);

        $browser = $this->login($user->getEmail());

        $crawler = $browser->request('GET', '/account/profile');

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $vatIdField = $crawler->filter('#vatIds');
        static::assertCount(1, $vatIdField);
        static::assertNull($vatIdField->attr('data-validation'));
        static::assertCount(0, $crawler->filter('label[for="vatIds"] .form-required-label'));
    }

    public function testGuestsSaveTheirVatIdInTheProfileAndReturnToCheckout(): void
    {
        $browser = $this->registerBusinessGuest('guest@example.com');

        $browser->request('GET', $_SERVER['APP_URL'] . '/account/profile?redirectTo=frontend.checkout.confirm.page');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        $browser->request('POST', $_SERVER['APP_URL'] . '/account/profile', [
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Contena',
            'vatIds' => ['DE123456789'],
            'redirectTo' => 'frontend.checkout.confirm.page',
        ]);

        $response = $browser->getResponse();
        static::assertTrue($response->isRedirect('/checkout/confirm'), (string) $response->getContent());
        static::assertSame(['DE123456789'], $this->getUserByEmail('guest@example.com')->getVatIds());
    }

    public function testGuestsCannotChangeCredentials(): void
    {
        $browser = $this->registerBusinessGuest('guest@example.com');

        $browser->request('POST', $_SERVER['APP_URL'] . '/account/profile/email', [
            'email' => [
                'email' => 'changed@example.com',
                'emailConfirmation' => 'changed@example.com',
                'password' => '',
            ],
        ]);

        $response = $browser->getResponse();
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertStringContainsString('/account/login', (string) $response->headers->get('Location'));
        static::assertSame('guest@example.com', $this->getUserByEmail('guest@example.com')->getEmail());
    }

    private function registerBusinessGuest(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('POST', $_SERVER['APP_URL'] . '/account/register', [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Contena',
            'email' => $email,
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'billingAddress' => [
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstraße 1',
                'zipcode' => '12345',
                'city' => 'Schöppingen',
            ],
        ]);

        static::assertTrue($this->getUserByEmail($email)->getGuest());

        return $browser;
    }

    private function getUserByEmail(string $email): UserEntity
    {
        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('email', $email));

        $user = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($user);

        return $user;
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createUser(Context $context): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'Germany'],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], $context);

        $user = $repo->search(new Criteria([$userId]), $context)->getEntities()->first();

        static::assertNotNull($user);

        return $user;
    }

    private function createBusinessUser(Context $context, bool $vatIdRequired): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Contena',
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'company' => 'Contena',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'VAT Test Country', 'vatIdRequired' => $vatIdRequired],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'business.user@test.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');
        $repo->create([$user], $context);

        $user = $repo->search(new Criteria([$userId]), $context)->getEntities()->first();

        static::assertNotNull($user);

        return $user;
    }

    private function getFlashBag(): FlashBagInterface
    {
        $session = $this->getSession();

        static::assertInstanceOf(Session::class, $session);

        return $session->getFlashBag();
    }
}
