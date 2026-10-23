<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class UserGroupRegistrationSeoUrlSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private const ROUTE_NAME = 'store-api.user-group-registration.config';

    /**
     * @var EntityRepository<UserGroupCollection>
     */
    private EntityRepository $userGroupRepository;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    protected function setUp(): void
    {
        $this->userGroupRepository = static::getContainer()->get('user_group.repository');
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
    }

    public function testUrlsAreWrittenForHeadlessChannelWithExternalFrontendDomain(): void
    {
        $channelId = $this->createHeadlessChannel(isExternalFrontend: true);
        $groupId = $this->createRegistrationUserGroup($channelId);

        $urls = $this->getHeadlessSeoUrls($groupId);

        static::assertCount(1, $urls);

        $url = $urls->first();

        static::assertNotNull($url);
        static::assertSame($channelId, $url->getChannelId());
        static::assertSame($groupId, $url->getForeignKey());
        static::assertSame(self::ROUTE_NAME, $url->getRouteName());
        static::assertSame('/store-api/user-group-registration/config/' . $groupId, $url->getPathInfo());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    public function testUrlsAreWrittenWhenDomainBecomesExternalFrontend(): void
    {
        $channelId = $this->createHeadlessChannel(isExternalFrontend: false);
        $groupId = $this->createRegistrationUserGroup($channelId);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));

        $this->updateDomainExternalFrontend($channelId, true);

        static::assertCount(1, $this->getHeadlessSeoUrls($groupId));
    }

    public function testUrlsAreRemovedWhenDomainStopsBeingExternalFrontend(): void
    {
        $channelId = $this->createHeadlessChannel(isExternalFrontend: true);
        $groupId = $this->createRegistrationUserGroup($channelId);

        static::assertCount(1, $this->getHeadlessSeoUrls($groupId));

        $this->updateDomainExternalFrontend($channelId, false);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));
    }

    public function testNoUrlsAreWrittenForFrontendChannels(): void
    {
        $channelId = $this->createChannel()['id'];
        $groupId = $this->createRegistrationUserGroup($channelId);

        static::assertCount(0, $this->getHeadlessSeoUrls($groupId));
    }

    private function createHeadlessChannel(bool $isExternalFrontend): string
    {
        return $this->createChannel([
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://frontend.' . Uuid::randomHex() . '.test',
                    'isExternalFrontend' => $isExternalFrontend,
                ],
            ],
        ])['id'];
    }

    private function createRegistrationUserGroup(string $channelId): string
    {
        $id = Uuid::randomHex();

        $this->userGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $channelId]],
            ],
        ], Context::createDefaultContext());

        return $id;
    }

    private function updateDomainExternalFrontend(string $channelId, bool $isExternalFrontend): void
    {
        $domainRepository = static::getContainer()->get('channel_domain.repository');

        $domainId = $domainRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('channelId', $channelId)),
            Context::createDefaultContext()
        )->firstId();

        static::assertNotNull($domainId);

        $domainRepository->update([
            [
                'id' => $domainId,
                'isExternalFrontend' => $isExternalFrontend,
            ],
        ], Context::createDefaultContext());
    }

    private function getHeadlessSeoUrls(string $foreignKey): SeoUrlCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('foreignKey', $foreignKey))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        return $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }
}
