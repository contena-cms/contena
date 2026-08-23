<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Seo;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Container;

trait FrontendChannelTestHelper
{
    use TenantTestBehaviour;

    public function getBrowserWithLoggedInMember(): KernelBrowser
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
            new Criteria()->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB)),
            Context::createDefaultContext()
        )->getEntities()->first();
        TestCase::assertNotNull($channel);

        $header = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $browser->setServerParameter($header, $channel->getAccessKey());
        $browser->setServerParameter('test-channel-id', $channel->getId());

        $memberId = Uuid::randomHex();
        $this->createMemberWithEmail($memberId, 'foo@foo.de', 'bar12345', $channel);
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
        ?string $categoryEntrypoint = null,
        ?Context $context = null,
    ): ChannelContext {
        $context ??= Context::createDefaultContext();

        /** @var EntityRepository<ChannelCollection> $repo */
        $repo = static::getContainer()->get('channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $domains = [];
        $languages = [];
        $countryId = $this->getValidCountryId(null);
        $memberGroupId = TestDefaults::FALLBACK_MEMBER_GROUP;
        $navigationCategoryId = $categoryEntrypoint ?? $this->getValidCategoryId();
        if ($context->getTenantId() !== null) {
            $references = $this->createTenantChannelReferences(
                $context,
                $name,
                navigationCategoryId: $categoryEntrypoint,
            );
            $memberGroupId = $references['memberGroupId'];
            $navigationCategoryId = $references['navigationCategoryId'];
        }

        foreach ($languageIds as $languageId) {
            $languages[] = ['id' => $languageId];
            $domains[] = [
                'languageId' => $languageId,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://example.com/' . $name . '/' . $languageId,
            ];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'accessKey' => Uuid::randomHex(),
            'languageId' => $defaultLanguageId,
            'countryId' => $countryId,
            'memberGroupId' => $memberGroupId,
            'languages' => $languages,
            'countries' => [['id' => $countryId]],
            'domains' => $domains,
            'navigationCategoryId' => $navigationCategoryId,
        ]], $context);

        $channel = $repo->search(new Criteria([$id]), $context)->getEntities()->first();
        TestCase::assertInstanceOf(ChannelEntity::class, $channel);

        return $this->createNewContext($channel);
    }

    public function updateChannelNavigationEntryPoint(string $id, string $categoryId): void
    {
        /** @var EntityRepository<ChannelCollection> $repo */
        $repo = static::getContainer()->get('channel.repository');

        $repo->update([['id' => $id, 'navigationCategoryId' => $categoryId]], Context::createDefaultContext());
    }

    private function createMemberWithEmail(string $memberId, string $email, string $password, ChannelEntity $channel): MemberEntity
    {
        /** @var Container $container */
        $container = static::getContainer();

        $member = [
            'id' => $memberId,
            'email' => $email,
            'password' => $password,
            'name' => 'foo bar',
            'groupId' => $channel->getMemberGroupId(),
            'channelId' => $channel->getId(),
            'languageId' => $channel->getLanguageId(),
            'memberNumber' => Uuid::randomHex(),
            'active' => true,
        ];

        /** @var EntityRepository<MemberCollection> $memberRepository */
        $memberRepository = $container->get('member.repository');
        $memberRepository->upsert([$member], Context::createDefaultContext());

        $member = $memberRepository->search(new Criteria([$memberId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }

    private function createNewContext(ChannelEntity $channel): ChannelContext
    {
        $factory = static::getContainer()->get(ChannelContextFactory::class);

        return $factory->create(Uuid::randomHex(), $channel->getId(), []);
    }
}
