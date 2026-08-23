<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Aggregate\MediaFolder;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    protected function setUp(): void
    {
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
    }

    public function testCreateMediaFolderWithConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $folderId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();

        $this->mediaFolderRepository->upsert([
            [
                'id' => $folderId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'createThumbnails' => true,
                ],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('configuration');

        $collection = $this->mediaFolderRepository->search($criteria, $context)->getEntities();

        $mediaFolder = $collection->get($folderId);

        static::assertInstanceOf(MediaFolderEntity::class, $mediaFolder);
        static::assertSame('default folder', $mediaFolder->getName());
        static::assertNotNull($mediaFolder->getConfigurationId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertTrue($mediaFolder->getConfiguration()->getCreateThumbnails());
    }

    public function testCreatedMediaFolderIsSetInConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $folderId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();

        $this->mediaFolderRepository->upsert([
            [
                'id' => $folderId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'createThumbnails' => true,
                ],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolders');

        $mediaFolderConfigurationRepository = static::getContainer()->get('media_folder_configuration.repository');
        $collection = $mediaFolderConfigurationRepository->search($criteria, $context)->getEntities();

        $configuration = $collection->get($configurationId);
        static::assertInstanceOf(MediaFolderConfigurationEntity::class, $configuration);
        static::assertInstanceOf(MediaFolderCollection::class, $configuration->getMediaFolders());
        static::assertNotNull($configuration->getMediaFolders()->get($folderId));
    }
}
