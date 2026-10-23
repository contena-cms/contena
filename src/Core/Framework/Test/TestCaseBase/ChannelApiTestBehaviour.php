<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;

trait ChannelApiTestBehaviour
{
    use BasicTestDataBehaviour;

    /**
     * @var array<string>
     */
    protected array $channelIds = [];

    private ?KernelBrowser $channelApiBrowser = null;

    #[After]
    public function resetChannelApiTestCaseTrait(): void
    {
        if (!$this->channelApiBrowser) {
            return;
        }

        $connection = $this->channelApiBrowser
            ->getContainer()
            ->get(Connection::class);

        try {
            $connection->executeStatement(
                'DELETE FROM channel WHERE id IN (:channelIds)',
                ['channelIds' => $this->channelIds],
                ['channelIds' => ArrayParameterType::BINARY]
            );
        } catch (\Exception $ex) {
            // nth
        }

        $this->channelIds = [];
        $this->channelApiBrowser = null;
    }

    public function getChannelApiChannelId(): string
    {
        if (!$this->channelIds) {
            throw new \LogicException('The sales channel id can only be requested after calling `createChannelApiClient`.');
        }

        return array_last($this->channelIds);
    }

    /**
     * @param array<mixed> $channelOverride
     */
    public function createCustomChannelBrowser(array $channelOverride = []): KernelBrowser
    {
        $kernel = $this->getKernel();
        $channelApiBrowser = KernelLifecycleManager::createBrowser($kernel);
        $channelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->authorizeChannelBrowser($channelApiBrowser, $channelOverride);

        return $channelApiBrowser;
    }

    /**
     * @param array<mixed> $channelOverride
     * @param array<mixed> $options
     */
    public function createChannelContext(array $channelOverride = [], array $options = []): ChannelContext
    {
        $channel = $this->createChannel($channelOverride);

        return $this->createContext($channel, $options);
    }

    public function login(?KernelBrowser $browser = null): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        if (!$browser) {
            $browser = $this->getChannelBrowser();
        }

        $browser
            ->request(
                'POST',
                '/channel-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        if ($contextToken === '') {
            throw new \RuntimeException('Cannot login with the given credential account');
        }

        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $userId;
    }

    abstract protected static function getKernel(): KernelInterface;

    protected function getChannelBrowser(): KernelBrowser
    {
        if ($this->channelApiBrowser) {
            return $this->channelApiBrowser;
        }

        return $this->channelApiBrowser = $this->createChannelBrowser();
    }

    /**
     * @param array<mixed> $channelOverrides
     */
    protected function createChannelBrowser(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false,
        array $channelOverrides = []
    ): KernelBrowser {
        if (!$kernel) {
            $kernel = $this->getKernel();
        }

        $channelApiBrowser = KernelLifecycleManager::createBrowser($kernel, $enableReboot);
        $channelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->authorizeChannelBrowser($channelApiBrowser, $channelOverrides);

        return $channelApiBrowser;
    }

    /**
     * @param array<string, mixed> $channelOverride
     *
     * @return array<string, mixed>
     */
    protected function createChannel(array $channelOverride = []): array
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();
        $shippingMethod = $this->getAvailableShippingMethod();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('domains.url', 'http://localhost'));
        $channelIds = $channelRepository->searchIds($criteria, Context::createDefaultContext());

        if (!isset($channelOverride['domains']) && $channelIds->firstId() !== null) {
            $channelRepository->delete([['id' => $channelIds->firstId()]], Context::createDefaultContext());
        }

        $channel = array_replace_recursive([
            'id' => $channelOverride['id'] ?? Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'name' => 'API Test case sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod->getId(),
            'paymentMethods' => [['id' => $paymentMethod->getId()]],
            'shippingMethodId' => $shippingMethod->getId(),
            'shippingMethods' => [['id' => $shippingMethod->getId()]],
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(null),
            'currencies' => [['id' => $channelOverride['currencyId'] ?? Defaults::CURRENCY]],
            'languages' => $channelOverride['languages'] ?? [['id' => Defaults::LANGUAGE_SYSTEM]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost',
                ],
            ],
            'countries' => $channelOverride['countries'] ?? array_map(
                static fn (string $id): array => ['id' => $id],
                $this->getTestChannelCountryIds()
            ),
        ], $channelOverride);

        $channelRepository->upsert([$channel], Context::createDefaultContext());

        return $channel;
    }

    /**
     * @param array<string, mixed> $userOverride
     */
    private function createUser(?string $email = null, ?bool $guest = false, array $userOverride = []): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        if ($email === null) {
            $email = Uuid::randomHex() . '@example.com';
        }

        $user = array_replace_recursive([
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => $guest,
            'userNumber' => '12345',
        ], $userOverride);

        $userId = $user['id'];

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        return $userId;
    }

    /**
     * @param array<string, string> $channel
     * @param array<string, mixed> $options
     */
    private function createContext(array $channel, array $options): ChannelContext
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $channel['id'], $options);

        return $context;
    }

    /**
     * @param array<string, mixed> $channelOverride
     */
    private function authorizeChannelBrowser(KernelBrowser $channelApiClient, array $channelOverride = []): void
    {
        $channel = $this->createChannel($channelOverride);

        $this->channelIds[] = $channel['id'];

        $header = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $channelApiClient->setServerParameter($header, $channel['accessKey']);
        $channelApiClient->setServerParameter('test-sales-channel-id', $channel['id']);
    }

    private function assignChannelContext(?KernelBrowser $customBrowser = null): void
    {
        $browser = $customBrowser ?: $this->getChannelBrowser();
        $browser->request('GET', '/channel-api/context');
        $content = $browser->getResponse()->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Response content is not a string');
        }
        $content = json_decode($content, true);
        if (isset($content['errors'])) {
            throw new \RuntimeException($content['errors'][0]['detail']);
        }
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content['token']);
    }

    /**
     * createUser() and login() use a country of the default sales channel for the user address, so test sales
     * channels get that country as well. Otherwise checkouts are correctly rejected as blocked. Some tests remove or
     * replace the default sales channel, then only the first valid country is assigned.
     *
     * @return list<string>
     */
    private function getTestChannelCountryIds(): array
    {
        $ids = [$this->getValidCountryId(null)];

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true))
            ->addFilter(new EqualsFilter('channels.id', TestDefaults::CHANNEL))
            ->addSorting(new FieldSorting('iso'));

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $userCountryId = $countryRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        if ($userCountryId !== null) {
            $ids[] = $userCountryId;
        }

        return array_values(array_unique($ids));
    }

    private function getRandomId(string $table): string
    {
        return (string) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }
}
