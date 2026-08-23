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
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;

trait ChannelApiTestBehaviour
{
    use BasicTestDataBehaviour;
    use TenantTestBehaviour;

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
        } catch (\Exception) {
            // nth
        }

        $this->channelIds = [];
        $this->channelApiBrowser = null;
    }

    public function getChannelApiChannelId(): string
    {
        if (!$this->channelIds) {
            throw new \LogicException('The channel id can only be requested after calling `createChannelApiClient`.');
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
        $browser ??= $this->getChannelBrowser();

        $email = Uuid::randomHex() . '@example.com';
        $memberId = $this->createChannelApiMember($email, [
            'channelId' => $browser->getServerParameter('test-channel-id', TestDefaults::CHANNEL),
        ]);

        $browser->request(
            'POST',
            '/channel-api/account/login',
            [
                'email' => $email,
                'password' => 'contenaAdmin',
            ]
        );

        $content = $browser->getResponse()->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Login response content is not a string');
        }

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        if (isset($response['errors'])) {
            throw new \RuntimeException($response['errors'][0]['detail']);
        }

        $contextToken = $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        if ($contextToken === '') {
            throw new \RuntimeException('Cannot login with the given credential account');
        }

        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $memberId;
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
        $kernel ??= $this->getKernel();

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
    protected function createChannel(array $channelOverride = [], ?Context $context = null): array
    {
        $context ??= Context::createDefaultContext();

        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $defaultDomainUrl = 'http://localhost';
        if ($context->getTenantId() !== null) {
            $defaultDomainUrl = 'http://' . Uuid::randomHex() . '.localhost';
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('domains.url', $defaultDomainUrl));
        $channelIds = $channelRepository->searchIds($criteria, $context);

        if (!isset($channelOverride['domains']) && $channelIds->firstId() !== null) {
            $channelRepository->delete([['id' => $channelIds->firstId()]], $context);
        }

        $countryId = $this->getValidCountryId(null);
        $memberGroupId = $channelOverride['memberGroupId'] ?? TestDefaults::FALLBACK_MEMBER_GROUP;
        $navigationCategoryId = $channelOverride['navigationCategoryId'] ?? $this->getValidCategoryId();
        if ($context->getTenantId() !== null) {
            $references = $this->createTenantChannelReferences(
                $context,
                (string) ($channelOverride['name'] ?? 'API test case channel'),
                \is_string($channelOverride['memberGroupId'] ?? null) ? $channelOverride['memberGroupId'] : null,
                \is_string($channelOverride['navigationCategoryId'] ?? null) ? $channelOverride['navigationCategoryId'] : null,
            );
            $memberGroupId = $references['memberGroupId'];
            $navigationCategoryId = $references['navigationCategoryId'];
        }

        $channel = array_replace_recursive([
            'id' => $channelOverride['id'] ?? Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'name' => 'API Test case channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'memberGroupId' => $memberGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'countryId' => $countryId,
            'languages' => $channelOverride['languages'] ?? [['id' => Defaults::LANGUAGE_SYSTEM]],
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $defaultDomainUrl,
                ],
            ],
            'countries' => [['id' => $countryId]],
        ], $channelOverride);

        $channelRepository->upsert([$channel], $context);

        return $channel;
    }

    /**
     * @param array<string, mixed> $memberOverride
     */
    private function createChannelApiMember(?string $email = null, array $memberOverride = []): string
    {
        $memberId = Uuid::randomHex();
        $email ??= Uuid::randomHex() . '@example.com';

        $member = array_replace_recursive([
            'id' => $memberId,
            'channelId' => TestDefaults::CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ], $memberOverride);

        $memberId = $member['id'];

        /** @var EntityRepository<MemberCollection> $memberRepository */
        $memberRepository = static::getContainer()->get('member.repository');
        $memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }

    /**
     * @param array<string, string> $channel
     * @param array<string, mixed> $options
     */
    private function createContext(array $channel, array $options): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $channel['id'], $options);
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
        $channelApiClient->setServerParameter('test-channel-id', $channel['id']);
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

    private function getRandomId(string $table): string
    {
        return (string) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }
}
