<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Seo;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Container;

trait FrontendChannelTestHelper
{
    public function getBrowserWithLoggedInUser(): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
        ]);

        /** @var Container $container */
        $container = static::getContainer();

        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = $container->get('channel.repository');
        $channel = $channelRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND)),
            Context::createDefaultContext()
        )->getEntities()->first();
        TestCase::assertNotNull($channel);

        $header = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $browser->setServerParameter($header, $channel->getAccessKey());
        $browser->setServerParameter('test-sales-channel-id', $channel->getId());

        $userId = Uuid::randomHex();
        $this->createUserWithEmail($userId, 'foo@foo.de', 'bar12345', $channel);
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            [
                'username' => 'foo@foo.de',
                'password' => 'bar12345',
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode());

        return $browser;
    }

    /**
     * @param array<string> $languageIds
     */
    public function createFrontendChannelContext(
        string $id,
        string $name,
        string $defaultLanguageId = Defaults::LANGUAGE_SYSTEM,
        array $languageIds = [],
        ?string $categoryEntrypoint = null
    ): ChannelContext {
        /** @var EntityRepository<ChannelCollection> $repo */
        $repo = static::getContainer()->get('channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $domains = [];
        $languages = [];

        $paymentMethod = $this->getValidPaymentMethodId();
        $shippingMethod = $this->getValidShippingMethodId();
        $country = $this->getValidCountryId(null);

        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
            $domains[] = [
                'languageId' => $langId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://example.com/' . $name . '/' . $langId,
            ];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'accessKey' => Uuid::randomHex(),
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod,
            'shippingMethodId' => $shippingMethod,
            'countryId' => $country,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $languages,
            'paymentMethods' => [['id' => $paymentMethod]],
            'shippingMethods' => [['id' => $shippingMethod]],
            'countries' => [['id' => $country]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => $domains,
            'navigationCategoryId' => !$categoryEntrypoint ? $this->getValidCategoryId() : $categoryEntrypoint,
        ]], Context::createDefaultContext());

        /** @var ChannelEntity $channel */
        $channel = $repo->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        return $this->createNewContext($channel);
    }

    public function updateChannelNavigationEntryPoint(string $id, string $categoryId): void
    {
        /** @var EntityRepository<ChannelCollection> $repo */
        $repo = static::getContainer()->get('channel.repository');

        $repo->update([['id' => $id, 'navigationCategoryId' => $categoryId]], Context::createDefaultContext());
    }

    private function createUserWithEmail(string $userId, string $email, string $password, ChannelEntity $channel): UserEntity
    {
        /** @var Container $container */
        $container = static::getContainer();

        $defaultBillingAddress = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'name' => 'test',
            'email' => $email,
            'password' => $password,
            'name' => 'foo',
            'lastName' => 'bar',
            'groupId' => $channel->getUserGroupId(),
            'salutationId' => $this->getValidSalutationId(),
            'channelId' => $channel->getId(),
            'defaultBillingAddress' => [
                'id' => $defaultBillingAddress,
                'countryId' => $channel->getCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'name' => 'foo',
                'lastName' => 'bar',
                'zipcode' => '48599',
                'city' => 'gronau',
                'street' => 'Schillerstr.',
            ],
            'defaultShippingAddressId' => $defaultBillingAddress,
            'userNumber' => 'asdf',
        ];

        /** @var EntityRepository<UserCollection> */
        $userRepository = $container->get('user.repository');
        $userRepository->upsert([$user], Context::createDefaultContext());

        $user = $userRepository->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);

        return $user;
    }

    private function createNewContext(ChannelEntity $channel): ChannelContext
    {
        $factory = static::getContainer()->get(ChannelContextFactory::class);

        $context = $factory->create(Uuid::randomHex(), $channel->getId(), []);

        $ruleLoader = static::getContainer()->get(CartRuleLoader::class);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
