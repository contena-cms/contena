<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Validation;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Sync\SyncBehavior;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    public function testInsertRequiresDefaultLanguageInLanguageList(): void
    {
        $id = Uuid::randomHex();

        try {
            $this->channelRepository->create([$this->getChannelData($id)], Context::createDefaultContext());
            static::fail('Expected the Channel language validator to reject the missing language mapping.');
        } catch (WriteException $exception) {
            static::assertStringContainsString(
                \sprintf('The channel with id "%s" does not have a default channel language id in the language list.', $id),
                $exception->getMessage(),
            );
        }
    }

    public function testInsertSucceedsWhenDefaultLanguageIsInLanguageList(): void
    {
        $id = Uuid::randomHex();
        $this->channelRepository->create([
            $this->getChannelData($id, [Defaults::LANGUAGE_SYSTEM]),
        ], Context::createDefaultContext());

        $channel = $this->channelRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertSame($id, $channel->getId());
    }

    public function testUpdateCannotSetDefaultLanguageOutsideLanguageList(): void
    {
        $id = Uuid::randomHex();
        $this->channelRepository->create([
            $this->getChannelData($id, [Defaults::LANGUAGE_SYSTEM]),
        ], Context::createDefaultContext());

        $deLanguageId = $this->getEnglishLanguageId();

        try {
            $this->channelRepository->update([[
                'id' => $id,
                'languageId' => $deLanguageId,
            ]], Context::createDefaultContext());
            static::fail('Expected the Channel language validator to reject the missing language mapping.');
        } catch (WriteException $exception) {
            static::assertStringContainsString(
                \sprintf('Cannot update default language id because the given id is not in the language list of channel with id "%s"', $id),
                $exception->getMessage(),
            );
        }
    }

    public function testDefaultLanguageCannotBeDeletedFromLanguageList(): void
    {
        $id = Uuid::randomHex();
        $this->channelRepository->create([
            $this->getChannelData($id, [Defaults::LANGUAGE_SYSTEM]),
        ], Context::createDefaultContext());

        $languageRepository = static::getContainer()->get('channel_language.repository');

        try {
            $languageRepository->delete([[
                'channelId' => $id,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ]], Context::createDefaultContext());
            static::fail('Expected the Channel language validator to reject deleting the default language.');
        } catch (WriteException $exception) {
            static::assertStringContainsString(
                \sprintf('Cannot delete default language id from language list of the channel with id "%s".', $id),
                $exception->getMessage(),
            );
        }
    }

    public function testChangingTheDefaultLanguageAndRemovingThePreviousDefaultInOneWrite(): void
    {
        $id = Uuid::randomHex();
        $newDefaultId = $this->getEnglishLanguageId();
        $context = Context::createDefaultContext();

        $this->channelRepository->create([
            $this->getChannelData($id, [Defaults::LANGUAGE_SYSTEM, $newDefaultId]),
        ], $context);

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('write', ChannelDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                ['id' => $id, 'languageId' => $newDefaultId],
            ]),
            new SyncOperation('delete', ChannelLanguageDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [
                ['channelId' => $id, 'languageId' => Defaults::LANGUAGE_SYSTEM],
            ]),
        ], $context, new SyncBehavior());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('languages');

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($channel);
        static::assertSame($newDefaultId, $channel->getLanguageId());
        static::assertNotNull($channel->getLanguages());
        static::assertSame([$newDefaultId], array_values($channel->getLanguages()->getIds()));
    }

    /**
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    private function getChannelData(string $id, array $languages = []): array
    {
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'countryId' => $countryId,
            'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'name' => 'Channel validator test',
            'countries' => [['id' => $countryId]],
        ];

        $data['languages'] = array_map(static fn (string $languageId): array => ['id' => $languageId], $languages);

        return $data;
    }

    private function getEnglishLanguageId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', 'en-GB'));

        $id = static::getContainer()->get('language.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
