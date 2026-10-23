<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\EventListener;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @internal
 */
class BlogExportEventListenerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $blogExportRepository;

    private Context $context;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        $this->blogExportRepository = static::getContainer()->get('blog_export.repository');
        $this->context = Context::createDefaultContext();
        $this->fileSystem = static::getContainer()->get('contena.filesystem.private');
        $this->ensureBlogStream();
    }

    public function testEditResetsIsRunningAndDeletesFile(): void
    {
        $exportId = $this->createTestEntity('EventListenerTest.csv');

        // Create an existing export file
        $filePath = \sprintf('%s/%s', static::getContainer()->getParameter('blog_export.directory'), 'EventListenerTest.csv');
        $this->fileSystem->write($filePath, 'dummy');
        static::assertTrue($this->fileSystem->fileExists($filePath));

        // Mark as running to simulate stuck state
        $this->blogExportRepository->update([[
            'id' => $exportId,
            'isRunning' => true,
        ]], $this->context);

        // Update an unrelated property to trigger the listener
        $this->blogExportRepository->update([[
            'id' => $exportId,
            'interval' => 999,
        ]], $this->context);

        // Assert file deleted and flags reset
        static::assertFalse($this->fileSystem->fileExists($filePath));

        $entity = $this->blogExportRepository->search(new Criteria([$exportId]), $this->context)->getEntities()->first();
        static::assertNotNull($entity);
        static::assertFalse($entity->getIsRunning());
        static::assertNull($entity->getGeneratedAt());
    }

    private function ensureBlogStream(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $exists = (bool) $connection->fetchOne('SELECT COUNT(*) FROM content_stream WHERE id = UNHEX(?)', ['137b079935714281ba80b40f83f8d7eb']);
        if ($exists) {
            return;
        }

        $connection->executeStatement('
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), \'[]\', 0, \'2019-08-16 08:43:57.488\', NULL);
        ');
    }

    private function getChannelId(): string
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $id = $repository->search(new Criteria(), $this->context)->getEntities()->first()?->getId();
        static::assertIsString($id);

        return $id;
    }

    private function getChannelDomain(): ChannelDomainEntity
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('channel');

        $domainEntity = $repository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($domainEntity);

        return $domainEntity;
    }

    private function createTestEntity(string $filename): string
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => $filename,
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 10,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ blog.name }}',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => $this->getChannelDomain()->getChannelId(),
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomain()->getId(),
                'generatedAt' => null,
                'generateByCronjob' => true,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }
}
