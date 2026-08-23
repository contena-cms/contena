<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Commands\DeleteThumbnailsCommand;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Test\Media\MediaFixtures;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class DeleteThumbnailsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaThumbnailCollection>
     */
    private EntityRepository $thumbnailRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->thumbnailRepository = static::getContainer()->get('media_thumbnail.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testDeletesPlatformAndTenantThumbnails(): void
    {
        $platformContext = Context::createDefaultContext();
        $this->setFixtureContext($platformContext);
        $platformMedia = $this->getMediaWithThumbnail();

        $tenantContext = $this->createTenantContext($this->createTenant('Delete thumbnails tenant'));
        $this->setFixtureContext($tenantContext);
        $tenantMediaId = Uuid::randomHex();
        $this->mediaRepository->create([[
            'id' => $tenantMediaId,
            'thumbnails' => [[
                'id' => Uuid::randomHex(),
                'width' => 200,
                'height' => 200,
                'mediaThumbnailSizeId' => $this->thumbnailSize200Id,
            ]],
        ]], $tenantContext);

        foreach ([$platformMedia->getId(), $tenantMediaId] as $mediaId) {
            $this->connection->update(
                'media',
                ['thumbnails_ro' => serialize(['stale'])],
                ['id' => Uuid::fromHexToBytes($mediaId)],
            );
        }

        static::assertNotSame([], $this->thumbnailRepository->searchIds(new Criteria(), $platformContext)->getIds());
        static::assertNotSame([], $this->thumbnailRepository->searchIds(new Criteria(), $tenantContext)->getIds());

        $command = new DeleteThumbnailsCommand(
            $this->connection,
            $this->thumbnailRepository,
            static::getContainer()->get('contena.filesystem.public'),
            static::getContainer()->get('contena.filesystem.private'),
            static::getContainer()->get(TenantScopeContextProvider::class),
            true,
        );

        new CommandTester($command)->execute([]);

        static::assertSame([], $this->thumbnailRepository->searchIds(new Criteria(), $platformContext)->getIds());
        static::assertSame([], $this->thumbnailRepository->searchIds(new Criteria(), $tenantContext)->getIds());

        foreach ([$platformMedia->getId(), $tenantMediaId] as $mediaId) {
            $row = $this->connection->fetchAssociative(
                'SELECT `thumbnails_ro` FROM `media` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($mediaId)],
            );

            static::assertSame(['thumbnails_ro' => null], $row);
        }
    }
}
