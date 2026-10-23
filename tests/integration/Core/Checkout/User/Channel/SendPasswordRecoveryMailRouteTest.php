<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Event\PasswordRecoveryUrlEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testResetUnknownEmail(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'lol@lol.de',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        /** @var array<string, mixed> $response */
        $response = \json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public function testResetWithInvalidUrl(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'lol@lol.de',
                    'frontendUrl' => 'http://aaaa.de',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithTryingToDisableValidation(): void
    {
        $this->createUser('foo-test@test.de');

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password?validateFrontendUrl=false',
                [
                    'email' => 'foo-test@test.de',
                    'frontendUrl' => 'http://my-evil-page',
                    'validateFrontendUrl' => false,
                ]
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithDisabledAccount(): void
    {
        $email = 'test-disabled@test.de';

        $this->createUser($email, false);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password?validateFrontendUrl=false',
                [
                    'email' => $email,
                    'frontendUrl' => 'http://localhost',
                    'validateFrontendUrl' => false,
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $response */
        $response = \json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    /**
     * @param array{domain: string, expectDomain: string} $domainUrlTest
     */
    #[DataProvider('sendMailWithDomainAndLeadingSlashProvider')]
    public function testSendMailWithDomainAndLeadingSlash(array $domainUrlTest): void
    {
        $this->createUser('foo-test@test.de');

        $this->addDomain($domainUrlTest['domain']);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserAccountRecoverRequestEvent::EVENT_NAME,
            static function (UserAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'frontendUrl' => $domainUrlTest['expectDomain'],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        /** @var UserAccountRecoverRequestEvent $caughtEvent */
        static::assertInstanceOf(UserAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://my-evil-page/account/', $caughtEvent->getResetUrl());
    }

    public function testSendMailWithChangedUrl(): void
    {
        $this->createUser('foo-test@test.de');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.pwdRecoverUrl', '/test/rec/password/%%RECOVERHASH%%"');

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserAccountRecoverRequestEvent::EVENT_NAME,
            static function (UserAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PasswordRecoveryUrlEvent::class,
            static function (PasswordRecoveryUrlEvent $event): void {
                $event->setRecoveryUrl($event->getRecoveryUrl() . '/?somethingSpecial=1');
            }
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        /** @var UserAccountRecoverRequestEvent $caughtEvent */
        static::assertInstanceOf(UserAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://localhost/test/rec/password/', $caughtEvent->getResetUrl());
        static::assertStringEndsWith('/?somethingSpecial=1', $caughtEvent->getResetUrl());
    }

    /**
     * @return iterable<string, array{array{domain: string, expectDomain: string}}>
     */
    public static function sendMailWithDomainAndLeadingSlashProvider(): iterable
    {
        yield 'domain without trailing slash is used unchanged' => [
            ['domain' => 'http://my-evil-page', 'expectDomain' => 'http://my-evil-page'],
        ];
        yield 'domain with trailing slash is normalized' => [
            ['domain' => 'http://my-evil-page/', 'expectDomain' => 'http://my-evil-page'],
        ];
        yield 'domain with double trailing slash is normalized' => [
            ['domain' => 'http://my-evil-page//', 'expectDomain' => 'http://my-evil-page'],
        ];
    }

    private function addDomain(string $url): void
    {
        $snippetSetId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM snippet_set LIMIT 1');

        $domain = [
            'channelId' => $this->ids->create('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'url' => $url,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $snippetSetId,
        ];

        static::getContainer()->get('channel_domain.repository')
            ->create([$domain], Context::createDefaultContext());
    }

    private function createUser(?string $email = null, bool $active = true): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'active' => $active,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());

        return $userId;
    }
}
