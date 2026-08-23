<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Contena\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Contena\Core\Content\Media\Thumbnail\ThumbnailService;
use Contena\Core\Content\Test\Media\MediaFixtures;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class GenerateThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private GenerateThumbnailsCommand $thumbnailCommand;

    private Context $context;

    /**
     * @var list<string>
     */
    private array $initialMediaIds;

    private bool $remoteThumbnailsEnable = false;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->context = Context::createDefaultContext();
        $this->remoteThumbnailsEnable = static::getContainer()->getParameter('contena.media.remote_thumbnails.enable');

        $this->thumbnailCommand = static::getContainer()->get(GenerateThumbnailsCommand::class);

        $ids = $this->mediaRepository->searchIds(new Criteria(), $this->context)->getIds();
        $this->initialMediaIds = $ids;
    }

    public function testExecuteHappyPath(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->createValidMediaFiles();

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/.*Generated\s*2.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

        $medias = $this->getNewMediaEntities();
        foreach ($medias as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertNotNull($thumbnails);
            static::assertCount(2, $thumbnails);

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($thumbnail);
            }
        }
    }

    public function testExecuteWithCustomLimit(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->createValidMediaFiles();

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute(['-b' => '2']);

        $string = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/.*Generated\s*2.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . \count($this->initialMediaIds) . '.*/', $string);

        $medias = $this->getNewMediaEntities();
        foreach ($medias as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertNotNull($thumbnails);
            static::assertCount(2, $thumbnails);

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($thumbnail);
            }
        }
    }

    public function testItSkipsNotSupportedMediaTypes(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->createNotSupportedMediaFiles();

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute([]);

        $string = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/.*Generated\s*1.*/', $string);
        static::assertMatchesRegularExpression('/.*Skipped\s*' . (\count($this->initialMediaIds) + 1) . '.*/', $string);

        $medias = $this->getNewMediaEntities();
        foreach ($medias as $updatedMedia) {
            if (str_starts_with((string) $updatedMedia->getMimeType(), 'image')) {
                $thumbnails = $updatedMedia->getThumbnails();
                static::assertNotNull($thumbnails);
                static::assertCount(2, $thumbnails);

                foreach ($thumbnails as $thumbnail) {
                    $this->assertThumbnailExists($thumbnail);
                }
            }
        }
    }

    public function testHappyPathWithGivenFolderName(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->createValidMediaFiles();

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute(['--folder-name' => 'test folder']);

        $medias = $this->getNewMediaEntities();
        foreach ($medias as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertNotNull($thumbnails);
            static::assertCount(2, $thumbnails);

            foreach ($thumbnails as $thumbnail) {
                $this->assertThumbnailExists($thumbnail);
            }
        }
    }

    public function testExecuteHappyPathWithRemoteThumbnailsEnable(): void
    {
        if (!$this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is disabled');
        }

        $this->createValidMediaFiles();

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute([]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testSkipsMediaEntitiesFromDifferentFolders(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->createValidMediaFiles();
        $this->mediaFolderRepository->create([
            [
                'name' => 'folder-to-search',
                'useParentConfiguration' => false,
                'configuration' => [],
            ],
        ], $this->context);

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute(['--folder-name' => 'folder-to-search']);

        $medias = $this->getNewMediaEntities();
        foreach ($medias as $updatedMedia) {
            $thumbnails = $updatedMedia->getThumbnails();
            static::assertNotNull($thumbnails);
            static::assertCount(0, $thumbnails);
        }
    }

    public function testCommandAbortsIfNoFolderCanBeFound(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->expectExceptionObject(MediaException::mediaFolderNameNotFound('non-existing-folder'));

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute(['--folder-name' => 'non-existing-folder']);
    }

    public function testItThrowsExceptionOnNonNumericLimit(): void
    {
        if ($this->remoteThumbnailsEnable) {
            static::markTestSkipped('Remote thumbnails is enabled. Skipping thumbnail generation test.');
        }

        $this->expectExceptionObject(MediaException::invalidBatchSize());

        $commandTester = new CommandTester($this->thumbnailCommand);
        $commandTester->execute(['--batch-size' => 'test']);
    }

    public function testItCallsUpdateThumbnailsWithStrictArgument(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $thumbnailServiceMock = $this->createMock(ThumbnailService::class);

        $thumbnailServiceMock->expects($this->exactly(\count($this->initialMediaIds) + $newMedia->count()))
            ->method('updateThumbnails')
            ->with(static::anything(), $this->context, true);

        $command = new GenerateThumbnailsCommand(
            $thumbnailServiceMock,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(TenantScopeContextProvider::class),
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--strict' => true]);
    }

    public function testItCallsUpdateThumbnailsWithoutStrictArgument(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $thumbnailServiceMock = $this->createMock(ThumbnailService::class);

        $thumbnailServiceMock->expects($this->exactly(\count($this->initialMediaIds) + $newMedia->count()))
            ->method('updateThumbnails')
            ->with(static::anything(), $this->context, false);

        $command = new GenerateThumbnailsCommand(
            $thumbnailServiceMock,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(TenantScopeContextProvider::class),
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    public function testItDispatchesUpdateThumbnailsMessageWithCorrectStrictProperty(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();

        $affectedMediaIds = [...array_combine($this->initialMediaIds, $this->initialMediaIds), ...$newMedia->getIds()];

        $expectedMessageStrict = new UpdateThumbnailsMessage();
        $expectedMessageStrict->setContext($this->context);

        $expectedMessageStrict->setStrict(true);
        $expectedMessageStrict->setMediaIds($affectedMediaIds);

        $expectedMessageNonStrict = new UpdateThumbnailsMessage();
        $expectedMessageNonStrict->setContext($this->context);

        $expectedMessageNonStrict->setStrict(false);
        $expectedMessageNonStrict->setMediaIds($affectedMediaIds);

        $messageBusMock = new CollectingMessageBus();

        $command = new GenerateThumbnailsCommand(
            static::getContainer()->get(ThumbnailService::class),
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $messageBusMock,
            static::getContainer()->get(TenantScopeContextProvider::class),
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--strict' => true, '--async' => true]);
        $commandTester->execute(['--async' => true]);
        $commandTester->execute(['--async' => true]);
        $commandTester->execute(['--strict' => true, '--async' => true]);

        $envelopes = $messageBusMock->getMessages();
        static::assertCount(4, $envelopes);

        static::assertEquals($expectedMessageStrict, $envelopes[0]->getMessage());
        static::assertEquals($expectedMessageNonStrict, $envelopes[1]->getMessage());
        static::assertEquals($expectedMessageNonStrict, $envelopes[2]->getMessage());
        static::assertEquals($expectedMessageStrict, $envelopes[3]->getMessage());
    }

    public function testItDispatchesUpdateThumbnailsMessageWithForceProperty(): void
    {
        $this->createValidMediaFiles();
        $newMedia = $this->getNewMediaEntities();
        $affectedMediaIds = [...array_combine($this->initialMediaIds, $this->initialMediaIds), ...$newMedia->getIds()];

        $messageBus = new CollectingMessageBus();
        $command = new GenerateThumbnailsCommand(
            static::getContainer()->get(ThumbnailService::class),
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $messageBus,
            static::getContainer()->get(TenantScopeContextProvider::class),
        );

        new CommandTester($command)->execute(['--async' => true, '--force' => true]);

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        $message = $messages[0]->getMessage();
        static::assertInstanceOf(UpdateThumbnailsMessage::class, $message);
        static::assertSame($affectedMediaIds, $message->getMediaIds());
        static::assertTrue($message->isForce());
        static::assertFalse($message->isStrict());
    }

    public function testAsyncMessagesKeepPlatformAndTenantContexts(): void
    {
        $this->setFixtureContext($this->context);
        $platformMedia = $this->getPngWithFolder();

        $tenantContext = $this->createTenantContext($this->createTenant('Thumbnail tenant'));
        $this->setFixtureContext($tenantContext);
        $tenantMedia = $this->getJpgWithFolder();

        $messageBus = new CollectingMessageBus();
        $command = new GenerateThumbnailsCommand(
            static::getContainer()->get(ThumbnailService::class),
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $messageBus,
            static::getContainer()->get(TenantScopeContextProvider::class),
        );

        new CommandTester($command)->execute([
            '--async' => true,
            '--folder-name' => 'test folder',
        ]);

        $messages = array_map(
            static function ($envelope): UpdateThumbnailsMessage {
                $message = $envelope->getMessage();
                static::assertInstanceOf(UpdateThumbnailsMessage::class, $message);

                return $message;
            },
            $messageBus->getMessages(),
        );

        static::assertCount(2, $messages);
        static::assertNull($messages[0]->getContext()->getTenantId());
        static::assertContains($platformMedia->getId(), $messages[0]->getMediaIds());
        static::assertSame($tenantContext->getTenantId(), $messages[1]->getContext()->getTenantId());
        static::assertContains($tenantMedia->getId(), $messages[1]->getMediaIds());
    }

    protected function assertThumbnailExists(MediaThumbnailEntity $thumbnail): void
    {
        static::assertTrue($this->getPublicFilesystem()->has($thumbnail->getPath()));
    }

    protected function createValidMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPng = $this->getPngWithFolder();
        $mediaJpg = $this->getJpgWithFolder();

        $filePath = $mediaPng->getPath();

        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/contena-logo.png', 'r')
        );

        $filePath = $mediaJpg->getPath();

        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/contena.jpg', 'r')
        );
    }

    protected function createNotSupportedMediaFiles(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPdf = $this->getPdf();
        $mediaJpg = $this->getJpgWithFolder();

        $this->mediaRepository->update([
            [
                'id' => $mediaPdf->getId(),
                'mediaFolderId' => $mediaJpg->getMediaFolderId(),
            ],
        ], $this->context);

        $filePath = $mediaPdf->getPath();

        $this->getPublicFilesystem()->writeStream(
            $filePath,
            fopen(__DIR__ . '/../fixtures/small.pdf', 'r')
        );

        $filePath = $mediaJpg->getPath();

        $this->getPublicFilesystem()->writeStream($filePath, fopen(__DIR__ . '/../fixtures/contena.jpg', 'r'));
    }

    private function getNewMediaEntities(): MediaCollection
    {
        if ($this->initialMediaIds !== []) {
            $criteria = new Criteria($this->initialMediaIds);
            $result = $this->mediaRepository->searchIds($criteria, $this->context);
            static::assertSame(\count($this->initialMediaIds), $result->getTotal());
        }

        $criteria = new Criteria();
        $criteria->addAssociation('thumbnails');
        if ($this->initialMediaIds !== []) {
            $criteria->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsAnyFilter('id', $this->initialMediaIds),
                ]
            ));
        }

        return $this->mediaRepository->search($criteria, $this->context)->getEntities();
    }
}
