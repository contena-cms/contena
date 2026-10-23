<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderCollection;
use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\Generator\MediaGenerator;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(MediaGenerator::class)]
class MediaGeneratorTest extends TestCase
{
    public function testGenerateWithoutDefaultFoldersCreatesNoMediaFolder(): void
    {
        /** @var StaticEntityRepository<MediaFolderCollection> $folderRepository */
        $folderRepository = new StaticEntityRepository([]);

        $generator = $this->createGenerator(
            [$this->searchResult([]), $this->searchResult([])],
            $folderRepository,
        );

        $generator->generate(0, $this->createContext());

        static::assertSame([], $folderRepository->upserts);
    }

    public function testGenerateCreatesMediaFolderForDefaultFolderWithoutOne(): void
    {
        $defaultFolder = new MediaDefaultFolderEntity();
        $defaultFolder->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<MediaFolderCollection> $folderRepository */
        $folderRepository = new StaticEntityRepository([]);

        $generator = $this->createGenerator(
            [$this->searchResult([$defaultFolder]), $this->searchResult([])],
            $folderRepository,
        );

        $generator->generate(0, $this->createContext());

        static::assertCount(1, $folderRepository->upserts);
        $upsert = $folderRepository->upserts[0][0];
        static::assertIsArray($upsert);
        static::assertSame($defaultFolder->getId(), $upsert['defaultFolderId']);
        static::assertSame('Blog Media', $upsert['name']);
    }

    public function testGenerateReusesExistingMediaFolderOfDefaultFolder(): void
    {
        $folder = new MediaFolderEntity();
        $folder->setId(Uuid::randomHex());

        $defaultFolder = new MediaDefaultFolderEntity();
        $defaultFolder->setId(Uuid::randomHex());
        $defaultFolder->setFolder($folder);

        /** @var StaticEntityRepository<MediaFolderCollection> $folderRepository */
        $folderRepository = new StaticEntityRepository([]);

        $generator = $this->createGenerator(
            [$this->searchResult([$defaultFolder]), $this->searchResult([])],
            $folderRepository,
        );

        $generator->generate(0, $this->createContext());

        static::assertSame([], $folderRepository->upserts);
    }

    /**
     * @param list<EntitySearchResult<MediaDefaultFolderCollection>> $defaultFolderResults
     * @param StaticEntityRepository<MediaFolderCollection> $folderRepository
     */
    private function createGenerator(array $defaultFolderResults, StaticEntityRepository $folderRepository): MediaGenerator
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        /** @var StaticEntityRepository<MediaDefaultFolderCollection> $defaultFolderRepository */
        $defaultFolderRepository = new StaticEntityRepository($defaultFolderResults);

        return new MediaGenerator(
            static::createStub(EntityWriterInterface::class),
            static::createStub(FileSaver::class),
            static::createStub(FileNameProvider::class),
            $defaultFolderRepository,
            $folderRepository,
            new MediaDefinition(),
            $connection,
        );
    }

    private function createContext(): DemodataContext
    {
        $context = static::createStub(DemodataContext::class);
        $context->method('getFaker')->willReturn(Factory::create());
        $context->method('getConsole')->willReturn(static::createStub(SymfonyStyle::class));
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }

    /**
     * @param list<MediaDefaultFolderEntity> $defaultFolders
     *
     * @return EntitySearchResult<MediaDefaultFolderCollection>
     */
    private function searchResult(array $defaultFolders): EntitySearchResult
    {
        return new EntitySearchResult(
            'media_default_folder',
            \count($defaultFolders),
            new MediaDefaultFolderCollection($defaultFolders),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
