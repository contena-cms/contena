<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class DoubleOptInServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testResendDoubleOptInMailSendsEmailWhenExpired(): void
    {
        $user = $this->createUser('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://shop.example.com', TestDefaults::CHANNEL);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);

        $updatedUser = $this->fetchUser($user->getId());
        static::assertNotNull($updatedUser->getDoubleOptInEmailSentDate());
        static::assertGreaterThan(new \DateTimeImmutable('-5 minutes'), $updatedUser->getDoubleOptInEmailSentDate());
    }

    public function testResendDoubleOptInMailUpdatesSentDateAfterSending(): void
    {
        $originalSentDate = new \DateTime('-2 days');
        $user = $this->createUser('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => $originalSentDate,
        ]);
        $context = Generator::generateChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://shop.example.com', TestDefaults::CHANNEL);

        $userId = $user->getId();
        $sentDateAtDispatch = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            function () use (&$sentDateAtDispatch, $userId): void {
                $sentDateAtDispatch = $this->fetchUser($userId)->getDoubleOptInEmailSentDate();
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($user, $context);

        static::assertNotNull($sentDateAtDispatch);
        static::assertLessThan(new \DateTimeImmutable('-1 day'), $sentDateAtDispatch);

        $updatedUser = $this->fetchUser($userId);
        static::assertNotNull($updatedUser->getDoubleOptInEmailSentDate());
        static::assertGreaterThan(new \DateTimeImmutable('-5 minutes'), $updatedUser->getDoubleOptInEmailSentDate());
    }

    public function testResolveDomainUrlFromSystemConfig(): void
    {
        $user = $this->createUser('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', 'https://configured-domain.example.com', TestDefaults::CHANNEL);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://configured-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlFallsBackToRepository(): void
    {
        $user = $this->createUser('c1', [
            'hash' => 'testhash',
            'guest' => false,
            'doubleOptInEmailSentDate' => new \DateTime('-2 days'),
        ]);
        $context = Generator::generateChannelContext();

        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInResendInterval', 24, TestDefaults::CHANNEL);
        $this->getSystemConfig()->set('core.loginRegistration.doubleOptInDomain', '', TestDefaults::CHANNEL);

        $domain = static::getContainer()->get('channel_domain.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('channelId', TestDefaults::CHANNEL))->setLimit(1),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertInstanceOf(ChannelDomainEntity::class, $domain);

        $dispatched = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService($eventDispatcher)->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertNotSame('', $domain->getUrl());
        static::assertStringStartsWith($domain->getUrl(), $dispatched->getConfirmUrl());
    }

    private function createService(EventDispatcher $eventDispatcher): DoubleOptInService
    {
        return new DoubleOptInService(
            static::getContainer()->get('user.repository'),
            $eventDispatcher,
            $this->getSystemConfig(),
            static::getContainer()->get('channel_domain.repository'),
            new NativeClock(),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(string $key, array $overrides = []): UserEntity
    {
        $builder = new UserBuilder($this->ids, $key);
        foreach ($overrides as $field => $value) {
            $builder->add($field, $value);
        }

        $userRepo = static::getContainer()->get('user.repository');
        $userRepo->create([$builder->build()], Context::createDefaultContext());

        return $this->fetchUser($this->ids->get($key));
    }

    private function fetchUser(string $id): UserEntity
    {
        $user = static::getContainer()->get('user.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()
            ->first();

        static::assertInstanceOf(UserEntity::class, $user);

        return $user;
    }

    private function getSystemConfig(): SystemConfigService
    {
        return static::getContainer()->get(SystemConfigService::class);
    }
}
