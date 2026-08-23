<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\DataDictionary;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemEntity;
use Contena\Core\System\DataDictionary\DataDictionaryCollection;
use Contena\Core\System\DataDictionary\DataDictionaryEntity;
use Contena\Core\System\DataDictionary\DataDictionaryLoaderInterface;
use Contena\Core\System\DataDictionary\DataDictionaryWriteValidator;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validator\UserGenderValidator;

/**
 * @internal
 */
class DataDictionaryRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<DataDictionaryCollection>
     */
    private EntityRepository $dictionaryRepository;

    /**
     * @var EntityRepository<DataDictionaryItemCollection>
     */
    private EntityRepository $itemRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private DataDictionaryLoaderInterface $loader;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->dictionaryRepository = static::getContainer()->get('data_dictionary.repository');
        $this->itemRepository = static::getContainer()->get('data_dictionary_item.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->loader = static::getContainer()->get(DataDictionaryLoaderInterface::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testPersistsTranslationsAndParentItems(): void
    {
        $dictionaryId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $technicalName = 'test.hierarchy.' . Uuid::randomHex();

        $this->dictionaryRepository->create([[
            'id' => $dictionaryId,
            'technicalName' => $technicalName,
            'label' => 'Hierarchy',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['label' => 'Hierarchy', 'description' => 'A test dictionary'],
            ],
            'items' => [
                [
                    'id' => $parentId,
                    'code' => 'parent',
                    'label' => 'Parent',
                    'position' => 20,
                    'value' => ['kind' => 'root'],
                    'customFields' => ['source' => 'integration-test'],
                ],
                [
                    'id' => $childId,
                    'parentId' => $parentId,
                    'code' => 'child',
                    'label' => 'Child',
                    'position' => 10,
                ],
            ],
        ]], Context::createDefaultContext());

        $dictionary = $this->loader->load($technicalName, Context::createDefaultContext());

        static::assertInstanceOf(DataDictionaryEntity::class, $dictionary);
        static::assertSame('Hierarchy', $dictionary->getLabel());
        static::assertSame('A test dictionary', $dictionary->getDescription());

        $items = $dictionary->getItems();
        static::assertNotNull($items);
        static::assertSame(['child', 'parent'], array_values($items->map(static fn (DataDictionaryItemEntity $item) => $item->getCode())));

        $criteria = new Criteria([$parentId])->addAssociation('children');
        $parent = $this->itemRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(DataDictionaryItemEntity::class, $parent);
        static::assertSame(['kind' => 'root'], $parent->getValue());
        static::assertSame(['source' => 'integration-test'], $parent->getCustomFields());
        static::assertNotNull($parent->getChildren());
        static::assertSame($childId, $parent->getChildren()->first()?->getId());
    }

    public function testSystemLockedTechnicalFieldsCannotBeChangedFromUserScope(): void
    {
        $dictionaryId = Uuid::randomHex();
        $itemId = Uuid::randomHex();

        $this->dictionaryRepository->create([[
            'id' => $dictionaryId,
            'technicalName' => 'test.locked.' . Uuid::randomHex(),
            'label' => 'Locked dictionary',
            'systemLocked' => true,
            'items' => [[
                'id' => $itemId,
                'code' => 'locked-code',
                'label' => 'Locked item',
                'systemLocked' => true,
            ]],
        ]], Context::createDefaultContext());

        $source = new AdminApiSource(null);
        $source->setIsAdmin(true);
        $userContext = Context::createDefaultContext($source);
        $this->dictionaryRepository->update([[
            'id' => $dictionaryId,
            'active' => false,
            'label' => 'Changed label',
        ]], $userContext);
        $this->itemRepository->update([[
            'id' => $itemId,
            'active' => false,
            'position' => 50,
            'label' => 'Changed item label',
        ]], $userContext);

        $dictionary = $this->dictionaryRepository->search(new Criteria([$dictionaryId]), $userContext)->getEntities()->first();
        static::assertInstanceOf(DataDictionaryEntity::class, $dictionary);
        static::assertFalse($dictionary->isActive());
        static::assertSame('Changed label', $dictionary->getLabel());

        $exception = null;
        try {
            $this->itemRepository->update([[
                'id' => $itemId,
                'code' => 'changed-code',
            ]], $userContext);
        } catch (WriteException $exception) {
        }

        self::assertViolation($exception, DataDictionaryWriteValidator::VIOLATION_SYSTEM_LOCKED);

        $exception = null;
        try {
            $this->dictionaryRepository->update([[
                'id' => $dictionaryId,
                'technicalName' => 'changed.technical-name',
            ]], $userContext);
        } catch (WriteException $exception) {
        }

        self::assertViolation($exception, DataDictionaryWriteValidator::VIOLATION_SYSTEM_LOCKED);

        $exception = null;
        try {
            $this->dictionaryRepository->delete([['id' => $dictionaryId]], $userContext);
        } catch (WriteException $exception) {
        }

        self::assertViolation($exception, DataDictionaryWriteValidator::VIOLATION_SYSTEM_LOCKED);
    }

    public function testTechnicalNameIsUnique(): void
    {
        $technicalName = 'test.unique.' . Uuid::randomHex();

        $this->dictionaryRepository->create([[
            'technicalName' => $technicalName,
            'label' => 'First',
        ]], Context::createDefaultContext());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->dictionaryRepository->create([[
            'technicalName' => $technicalName,
            'label' => 'Second',
        ]], Context::createDefaultContext());
    }

    public function testItemCodeIsUniqueWithinDictionary(): void
    {
        $dictionaryId = Uuid::randomHex();
        $this->dictionaryRepository->create([[
            'id' => $dictionaryId,
            'technicalName' => 'test.unique-code.' . Uuid::randomHex(),
            'label' => 'Unique codes',
        ]], Context::createDefaultContext());
        $this->itemRepository->create([[
            'dictionaryId' => $dictionaryId,
            'code' => 'duplicate',
            'label' => 'First',
        ]], Context::createDefaultContext());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->itemRepository->create([[
            'dictionaryId' => $dictionaryId,
            'code' => 'duplicate',
            'label' => 'Second',
        ]], Context::createDefaultContext());
    }

    public function testUserGenderStoresOnlyCoreGenderItemCode(): void
    {
        $otherDictionaryId = Uuid::randomHex();
        $this->dictionaryRepository->create([[
            'id' => $otherDictionaryId,
            'technicalName' => 'test.other-gender.' . Uuid::randomHex(),
            'label' => 'Other gender dictionary',
            'items' => [[
                'code' => 'other-gender-code',
                'label' => 'Other gender',
            ]],
        ]], Context::createDefaultContext());

        $localeId = $this->connection->fetchOne('SELECT LOWER(HEX(`id`)) FROM `locale` LIMIT 1');
        static::assertIsString($localeId);

        $userId = Uuid::randomHex();
        $this->userRepository->create([[
            'id' => $userId,
            'localeId' => $localeId,
            'username' => 'gender-test-' . $userId,
            'password' => 'integration-test-password',
            'name' => 'Gender Test',
            'email' => $userId . '@example.invalid',
            'gender' => 'male',
        ]], Context::createDefaultContext());

        $user = $this->userRepository->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame('male', $user->getGender());

        $exception = null;
        try {
            $this->userRepository->update([[
                'id' => $userId,
                'gender' => 'other-gender-code',
            ]], Context::createDefaultContext());
        } catch (WriteException $exception) {
        }

        self::assertViolation($exception, UserGenderValidator::VIOLATION_INVALID_GENDER);
    }

    public function testLoaderCacheIsInvalidatedAfterDalWrite(): void
    {
        $dictionaryId = Uuid::randomHex();
        $technicalName = 'test.cache.' . Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->dictionaryRepository->create([[
            'id' => $dictionaryId,
            'technicalName' => $technicalName,
            'label' => 'Before',
        ]], $context);

        $dictionary = $this->loader->load($technicalName, $context);
        static::assertInstanceOf(DataDictionaryEntity::class, $dictionary);
        static::assertSame('Before', $dictionary->getLabel());

        $this->dictionaryRepository->update([[
            'id' => $dictionaryId,
            'label' => 'After',
        ]], $context);

        $dictionary = $this->loader->load($technicalName, $context);
        static::assertInstanceOf(DataDictionaryEntity::class, $dictionary);
        static::assertSame('After', $dictionary->getLabel());
    }

    public function testDalWritesAreStoredInTheAuditLog(): void
    {
        $technicalName = 'test.audit.' . Uuid::randomHex();

        $this->dictionaryRepository->create([[
            'technicalName' => $technicalName,
            'label' => 'Audited dictionary',
        ]], Context::createDefaultContext());

        $context = $this->connection->fetchOne(
            'SELECT `context`
             FROM `log_entry`
             WHERE `channel` = :channel
               AND JSON_UNQUOTE(JSON_EXTRACT(`context`, "$.entity")) = :entity
             ORDER BY `created_at` DESC
             LIMIT 1',
            ['channel' => 'data_dictionary_audit', 'entity' => 'data_dictionary']
        );
        static::assertIsString($context);

        $decoded = json_decode($context, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);
        static::assertSame('data_dictionary.written', $decoded['event']);
        static::assertSame('data_dictionary', $decoded['entity']);
    }

    private static function assertViolation(?WriteException $exception, string $code): void
    {
        static::assertInstanceOf(WriteException::class, $exception);

        foreach ($exception->getExceptions() as $innerException) {
            if (!$innerException instanceof WriteConstraintViolationException) {
                continue;
            }

            $violations = $innerException->getViolations()->findByCodes($code);
            if (\count($violations) === 0) {
                continue;
            }

            static::assertSame($code, $violations->get(0)->getCode());

            return;
        }

        static::fail(\sprintf('Violation with code "%s" was not found.', $code));
    }
}
