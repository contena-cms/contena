<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use Contena\Core\Content\Media\DataAbstractionLayer\MediaIndexer;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MediaIndexerDataScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string PROJECTION_MARKER = 'projection-must-not-change';

    public function testHandleUpdatesOnlyTheExactDataScope(): void
    {
        $tenantAId = $this->createTenant('Media indexer scope A')->id;
        $tenantBId = $this->createTenant('Media indexer scope B')->id;

        $ids = [
            'platform' => $this->createMedia(Context::createDefaultContext()),
            'tenant-a' => $this->createMedia(Context::createTenantContext($tenantAId)),
            'tenant-b' => $this->createMedia(Context::createTenantContext($tenantBId)),
        ];

        $contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant A' => Context::createTenantContext($tenantAId),
            'tenant B' => Context::createTenantContext($tenantBId),
            'global read' => Context::createGlobalContext(),
        ];
        $expectedUpdates = [
            'platform' => [$ids['platform']],
            'tenant A' => [$ids['tenant-a']],
            'tenant B' => [$ids['tenant-b']],
            'global read' => [$ids['platform']],
        ];

        foreach ($contexts as $case => $context) {
            $this->resetProjection(array_values($ids));

            static::getContainer()->get(MediaIndexer::class)->handle(
                new MediaIndexingMessage(array_values($ids), $context),
            );

            $projectionById = $this->fetchProjection(array_values($ids));
            foreach ($ids as $id) {
                if (\in_array($id, $expectedUpdates[$case], true)) {
                    static::assertNotSame(self::PROJECTION_MARKER, $projectionById[$id], $case . ' must update its own scope');

                    continue;
                }

                static::assertSame(self::PROJECTION_MARKER, $projectionById[$id], $case . ' must not update another scope');
            }
        }
    }

    private function createMedia(Context $context): string
    {
        $id = Uuid::randomHex();
        $this->mediaRepository()->create([[
            'id' => $id,
            'fileName' => 'scope-' . $id . '.png',
            'mimeType' => 'image/png',
        ]], $context);

        return $id;
    }

    /**
     * @param list<string> $ids
     */
    private function resetProjection(array $ids): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE media SET thumbnails_ro = :marker WHERE id IN (:ids)',
            ['marker' => self::PROJECTION_MARKER, 'ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function fetchProjection(array $ids): array
    {
        /** @var array<string, string> $projectionById */
        $projectionById = static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), thumbnails_ro FROM media WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return $projectionById;
    }

    /**
     * @return EntityRepository<MediaCollection>
     */
    private function mediaRepository(): EntityRepository
    {
        return static::getContainer()->get('media.repository');
    }
}
