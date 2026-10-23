<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Sync\SyncBehavior;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ChannelValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel language id in the language list.';
    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of sales channel with id "%s"';
    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the sales channel with id "%s".';

    private const CURRENCY_INSERT_VALIDATION_MESSAGE = 'The sales channel with id "%s" does not have a default sales channel currency id in the currency list.';
    private const CURRENCY_UPDATE_VALIDATION_MESSAGE = 'Cannot update default currency id because the given id is not in the currency list of sales channel with id "%s"';
    private const CURRENCY_DELETE_VALIDATION_MESSAGE = 'Cannot delete default currency id from currency list of the sales channel with id "%s".';

    /**
     * @param list<array{0: string, 1: string, 2?: list<string>}> $inserts
     * @param list<string> $invalids
     * @param list<array{id: string}> $valids
     */
    #[DataProvider('getInsertValidationProvider')]
    public function testInsertValidation(array $inserts, array $invalids = [], array $valids = []): void
    {
        $exception = null;

        $deDeLanguageId = $this->getDeDeLanguageId();
        $channelCreationData = [];
        foreach ($inserts as $insert) {
            foreach ($insert[2] ?? [] as $key => $language) {
                if ($language === 'de-DE') {
                    $insert[2][$key] = $deDeLanguageId;
                }
            }

            $channelCreationData[] = $this->getChannelData(...$insert);
        }

        try {
            $this->getChannelRepository()->create($channelCreationData, Context::createDefaultContext());
        } catch (WriteException $exception) {
            // nth
        }

        if (!$invalids) {
            static::assertNull($exception);

            $this->getChannelRepository()->delete($valids, Context::createDefaultContext());

            return;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $message = $exception->getMessage();

        foreach ($invalids as $invalid) {
            $expectedError = \sprintf(self::INSERT_VALIDATION_MESSAGE, $invalid);
            static::assertStringContainsString($expectedError, $message);
        }

        $this->getChannelRepository()->delete($valids, Context::createDefaultContext());
    }

    public static function getInsertValidationProvider(): \Generator
    {
        $valid1 = Uuid::randomHex();

        yield 'Payload with single valid entry' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, ['de-DE', Defaults::LANGUAGE_SYSTEM]],
            ],
            [],
            [
                [
                    'id' => $valid1,
                ],
            ],
        ];

        $valid1 = Uuid::randomHex();
        $valid2 = Uuid::randomHex();
        yield 'Payload with multiple valid entries' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, 'de-DE']],
                [$valid2, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]],
            ],
            [],
            [
                [
                    'id' => $valid1,
                ],
                [
                    'id' => $valid2,
                ],
            ],
        ];

        $invalidId1 = Uuid::randomHex();

        yield 'Payload with single invalid entry' => [
            [
                [$invalidId1, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1],
        ];

        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with multiple invalid entries' => [
            [
                [$invalidId1, Defaults::LANGUAGE_SYSTEM],
                [$invalidId2, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1, $invalidId2],
        ];

        $valid1 = Uuid::randomHex();
        $invalidId1 = Uuid::randomHex();
        $invalidId2 = Uuid::randomHex();

        yield 'Payload with mixed entries' => [
            [
                [$valid1, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, 'de-DE']],
                [$invalidId1, Defaults::LANGUAGE_SYSTEM, ['de-DE']],
                [$invalidId2, Defaults::LANGUAGE_SYSTEM],
            ],
            [$invalidId1, $invalidId2],
            [
                [
                    'id' => $valid1,
                ],
            ],
        ];
    }

    /**
     * @param list<array{id: string, languageId: string, languages?: list<array{id: string}>}> $updates
     * @param list<string> $invalids
     * @param list<string> $inserts
     */
    #[DataProvider('getUpdateValidationProvider')]
    public function testUpdateValidation(array $updates, array $invalids = [], array $inserts = []): void
    {
        $deLangId = $this->getDeDeLanguageId();
        foreach ($updates as &$update) {
            if ($update['languageId'] === 'de-DE') {
                $update['languageId'] = $deLangId;
            }

            foreach ($update['languages'] ?? [] as $key => $language) {
                if ($language['id'] === 'de-DE') {
                    $update['languages'][$key]['id'] = $deLangId;
                }
            }
        }
        unset($update);

        $exception = null;

        foreach ($inserts as $id) {
            $this->getChannelRepository()->create([
                $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
            ], Context::createDefaultContext());
        }

        try {
            $this->getChannelRepository()->update($updates, Context::createDefaultContext());
        } catch (WriteException $exception) {
            // nth
        }

        if (!$invalids) {
            static::assertNull($exception);

            return;
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $message = $exception->getMessage();

        foreach ($invalids as $invalid) {
            $expectedError = \sprintf(self::UPDATE_VALIDATION_MESSAGE, $invalid);
            static::assertStringContainsString($expectedError, $message);
        }
    }

    public static function getUpdateValidationProvider(): \Generator
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        yield 'Update default language ids because they are in the language list' => [
            [
                [
                    'id' => $id1,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'id' => $id2,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
            ],
            [],
            [
                $id1,
                $id2,
            ],
        ];

        yield 'Cannot update default language ids because they are not in language list' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                ],
                [
                    'id' => $id2,
                    'languageId' => 'de-DE',
                ],
            ],
            [$id1, $id2],
            [$id1, $id2],
        ];

        yield 'Update one valid language and throw one exception' => [
            [
                [
                    'id' => $id1,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'id' => $id2,
                    'languageId' => 'de-DE',
                ],
            ],
            [$id2],
            [$id1, $id2],
        ];

        yield 'Update default language id and languages in same time' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                    'languages' => [['id' => 'de-DE']],
                ],
            ],
            [],
            [$id1, $id2],
        ];

        yield 'Update default language id and multiple languages in same time' => [
            [
                [
                    'id' => $id1,
                    'languageId' => 'de-DE',
                    'languages' => [
                        ['id' => 'de-DE'],
                        ['id' => Defaults::LANGUAGE_SYSTEM]],
                ],
            ],
            [],
            [$id1, $id2],
        ];
    }

    public function testPreventDeletionOfDefaultLanguageId(): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        try {
            $this->getChannelLanguageRepository()->delete([[
                'channelId' => TestDefaults::CHANNEL,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testChangingTheDefaultLanguageAndRemovingThePreviousDefaultInOneWrite(): void
    {
        $id = Uuid::randomHex();
        $newDefaultId = $this->getDeDeLanguageId();
        $context = Context::createDefaultContext();

        $this->getChannelRepository()->create([
            $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM, $newDefaultId]),
        ], $context);

        static::getContainer()->get(SyncService::class)->sync([
        ], $context, new SyncBehavior());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('languages');

        $channel = $this->getChannelRepository()->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($channel);
        static::assertSame($newDefaultId, $channel->getLanguageId());
        static::assertNotNull($channel->getLanguages());
        static::assertSame([$newDefaultId], array_values($channel->getLanguages()->getIds()));
    }

    public function testCurrencyValidationFailsWithoutCurrencyEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['currencies'] = [];

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        $this->createChannelAndRethrowConstraintViolation($data);
    }

    public function testCurrencyValidationFailsWhenUpdatingDefaultToUnassignedCurrency(): void
    {
        $id = Uuid::randomHex();
        $this->getChannelRepository()->create([
            $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]),
        ], Context::createDefaultContext());

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        try {
            $this->getChannelRepository()->update([[
                'id' => $id,
                'currencyId' => Uuid::randomHex(),
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    public function testCurrencyValidationPreventsDefaultCurrencyRemoval(): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        try {
            $this->getChannelCurrencyRepository()->delete([[
                'channelId' => TestDefaults::CHANNEL,
                'currencyId' => Defaults::CURRENCY,
            ]], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    public function testChangingDefaultCurrencyAndRemovingPreviousDefaultInOneWrite(): void
    {
        $id = Uuid::randomHex();
        $newDefaultId = $this->createCurrency();
        $context = Context::createDefaultContext();

        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['currencies'][] = ['id' => $newDefaultId];
        $this->getChannelRepository()->create([$data], $context);

        static::getContainer()->get(SyncService::class)->sync([
        ], $context, new SyncBehavior());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('currencies');

        $channel = $this->getChannelRepository()->search($criteria, $context)->getEntities()->first();

        static::assertNotNull($channel);
        static::assertSame($newDefaultId, $channel->getCurrencyId());
        static::assertNotNull($channel->getCurrencies());
        static::assertSame([$newDefaultId], array_values($channel->getCurrencies()->getIds()));
    }

    public function testDeletingChannelWillNotBeValidated(): void
    {
        $id = Uuid::randomHex();
        $channelData = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);

        $channelRepository = $this->getChannelRepository();

        $context = Context::createDefaultContext();

        $channelRepository->create([$channelData], $context);

        $channelRepository->delete([[
            'id' => $id,
        ]], Context::createDefaultContext());

        $result = $channelRepository->search(new Criteria([$id]), $context)->getEntities();
        static::assertCount(0, $result);
    }

    public function testAgenticCommerceChannelValidationFailsWithoutLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM);
        $data['typeId'] = Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE;

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        try {
            $this->getChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testAgenticCommerceChannelValidationSucceedsWithLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE;

        $this->getChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM channel_language WHERE channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(1, $count);
    }

    public function testBlogComparisonChannelValidationFailsWithoutLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM);
        $data['typeId'] = Defaults::CHANNEL_TYPE_BLOG_COMPARISON;

        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
            ]),
        ));

        try {
            $this->getChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            foreach ($e->getExceptions() as $inner) {
                throw $inner;
            }

            throw $e;
        }
    }

    public function testBlogComparisonChannelValidationSucceedsWithLanguageEntry(): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = Defaults::CHANNEL_TYPE_BLOG_COMPARISON;

        $this->getChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM channel_language WHERE channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(1, $count);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonTransactionalChannelTypeProvider(): iterable
    {
        yield 'blog comparison' => [Defaults::CHANNEL_TYPE_BLOG_COMPARISON];
        yield 'agentic commerce' => [Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE];
    }

    #[DataProvider('nonTransactionalChannelTypeProvider')]
    public function testNonTransactionalChannelTypeSucceedsWithoutDefaultCurrencyInCurrencyList(string $typeId): void
    {
        $id = Uuid::randomHex();
        $data = $this->getChannelData($id, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $data['typeId'] = $typeId;
        $data['currencies'] = [];

        $this->getChannelRepository()->create([$data], Context::createDefaultContext());

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM channel_currency WHERE channel_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame(0, $count);
    }

    public function testPluginChannelTypeWithoutRegistrationSucceedsWithoutDefaultCurrencyInCurrencyList(): void
    {
        $pluginTypeId = Uuid::randomHex();
        static::getContainer()->get('channel_type.repository')->create([
            ['id' => $pluginTypeId, 'name' => 'Plugin feed'],
        ], Context::createDefaultContext());

        $channelId = Uuid::randomHex();
        $channelWithoutCurrencies = $this->getChannelData($channelId, Defaults::LANGUAGE_SYSTEM, [Defaults::LANGUAGE_SYSTEM]);
        $channelWithoutCurrencies['typeId'] = $pluginTypeId;
        $channelWithoutCurrencies['currencies'] = [];

        $this->getChannelRepository()->create([$channelWithoutCurrencies], Context::createDefaultContext());

        $currencyAssignmentCount = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM channel_currency WHERE channel_id = :id', ['id' => Uuid::fromHexToBytes($channelId)]);

        static::assertSame(0, $currencyAssignmentCount);
    }

    /**
     * @param list<string> $languages
     *
     * @return array<string, mixed>
     */
    private function getChannelData(string $id, string $languageId, array $languages = []): array
    {
        $default = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'languageId' => $languageId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        if (!$languages) {
            $default['languages'] = $languages;

            return $default;
        }

        foreach ($languages as $language) {
            $default['languages'][] = ['id' => $language];
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createChannelAndRethrowConstraintViolation(array $data): void
    {
        try {
            $this->getChannelRepository()->create([$data], Context::createDefaultContext());
        } catch (WriteException $e) {
            $this->rethrowConstraintViolation($e);
        }
    }

    private function rethrowConstraintViolation(WriteException $exception): never
    {
        foreach ($exception->getExceptions() as $inner) {
            throw $inner;
        }

        throw $exception;
    }

    private function createCurrency(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('currency.repository')->create([[
            'id' => $id,
            'name' => 'Test currency ' . $id,
            'factor' => 1.0,
            'symbol' => '$',
            'isoCode' => 'T' . substr($id, 0, 2),
            'decimalPrecision' => 2,
            'shortName' => 'Test currency',
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return EntityRepository<ChannelCollection>
     */
    private function getChannelRepository(): EntityRepository
    {
        return static::getContainer()->get('channel.repository');
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function getChannelLanguageRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_language.repository');
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function getChannelCurrencyRepository(): EntityRepository
    {
        return static::getContainer()->get('channel_currency.repository');
    }
}
