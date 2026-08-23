<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\System\Member;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class MemberGroupSubscriberTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MemberGroupCollection>
     */
    private EntityRepository $memberGroupRepository;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoRepository;

    protected function setUp(): void
    {
        $this->memberGroupRepository = static::getContainer()->get('member_group.repository');
        $this->seoRepository = static::getContainer()->get('seo_url.repository');
    }

    public function testUrlsAreNotWritten(): void
    {
        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(0, $urls);
    }

    public function testUrlsAreWrittenToOnlyAssignedChannel(): void
    {
        $s1 = $this->createChannel()['id'];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(1, $urls);

        $url = $urls->first();

        static::assertNotNull($url);
        static::assertSame($s1, $url->getChannelId());
        static::assertSame($id, $url->getForeignKey());
        static::assertSame('frontend.account.member-group-registration.page', $url->getRouteName());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    public function testUrlsAreNotDeletedForAllAssignedChannels(): void
    {
        $channelIds = [
            $this->createChannel()['id'],
            $this->createChannel()['id'],
        ];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => array_map(
                    static fn (string $channelId): array => ['id' => $channelId],
                    $channelIds
                ),
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(2, $urls);
        static::assertEqualsCanonicalizing(
            $channelIds,
            array_values(array_map(static fn (SeoUrlEntity $url): ?string => $url->getChannelId(), $urls->getElements()))
        );

        foreach ($urls as $url) {
            static::assertFalse($url->getIsDeleted());
        }
    }

    public function testUrlsAreForHeadlessChannelAreHanldedCorrectly(): void
    {
        $s1 = $this->createChannel(['typeId' => Defaults::CHANNEL_TYPE_API])['id'];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(0, $urls);
    }

    public function testUrlsAreNotWrittenWhenRegistrationIsDisabled(): void
    {
        $s1 = $this->createChannel()['id'];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => false,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(0, $urls);
    }

    public function testUrlExistsForAllLanguages(): void
    {
        $s1 = $this->createChannel()['id'];

        $languageIds = array_values(static::getContainer()->get('language.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities()->getIds());

        $upsertLanguages = [];
        foreach ($languageIds as $id) {
            if ($id === Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            $upsertLanguages[] = ['id' => $id];
        }

        static::getContainer()->get('channel.repository')->upsert([
            [
                'id' => $s1,
                'languages' => $upsertLanguages,
            ],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(\count($languageIds), $urls);

        foreach ($languageIds as $languageId) {
            $foundUrl = false;

            foreach ($urls->getElements() as $url) {
                if ($url->getLanguageId() === $languageId) {
                    static::assertSame('test', $url->getSeoPathInfo());
                    static::assertSame($s1, $url->getChannelId());
                    $foundUrl = true;
                }
            }

            static::assertTrue($foundUrl, \sprintf('Cannot find url for language "%s"', $languageId));
        }
    }

    public function testCreatedUrlsAreDeletedWhenGroupIsDeleted(): void
    {
        $s1 = $this->createChannel()['id'];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        static::assertCount(1, $this->getSeoUrlsById($id));

        $this->memberGroupRepository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertCount(0, $this->getSeoUrlsById($id));
    }

    public function testSaveGroupAndEnableLaterChannels(): void
    {
        $s1 = $this->createChannel()['id'];

        $id = Uuid::randomHex();

        $this->memberGroupRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'registrationActive' => true,
                'registrationTitle' => 'test',
            ],
        ], Context::createDefaultContext());

        $this->memberGroupRepository->upsert([
            [
                'id' => $id,
                'registrationChannels' => [['id' => $s1]],
            ],
        ], Context::createDefaultContext());

        $urls = $this->getSeoUrlsById($id);

        static::assertCount(1, $urls);

        $url = $urls->first();

        static::assertNotNull($url);
        static::assertSame($s1, $url->getChannelId());
        static::assertSame($id, $url->getForeignKey());
        static::assertSame('frontend.account.member-group-registration.page', $url->getRouteName());
        static::assertSame('test', $url->getSeoPathInfo());
    }

    private function getSeoUrlsById(string $id): SeoUrlCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $id));

        /** @var SeoUrlCollection $result */
        $result = $this->seoRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return $result;
    }

    /**
     * @param array<string, mixed> $channelOverride
     *
     * @return array<string, mixed>
     */
    private function createChannel(array $channelOverride = []): array
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');
        $countryId = $this->getValidCountryId(null);
        $channel = array_merge([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'name' => 'API Test case channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $countryId,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
            'countries' => [['id' => $countryId]],
        ], $channelOverride);

        $channelRepository->upsert([$channel], Context::createDefaultContext());

        return $channel;
    }
}
