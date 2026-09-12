<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexingMessage;
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
class BlogIndexerDataScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string UPDATED_AT_MARKER = '2000-01-01 00:00:00.000000';

    public function testHandleUpdatesOnlyTheExactDataScope(): void
    {
        $tenantAId = $this->createTenant('Blog indexer scope A')->id;
        $tenantBId = $this->createTenant('Blog indexer scope B')->id;

        $ids = [
            'platform' => $this->createBlog(Context::createDefaultContext()),
            'tenant-a' => $this->createBlog(Context::createTenantContext($tenantAId)),
            'tenant-b' => $this->createBlog(Context::createTenantContext($tenantBId)),
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
            $this->resetUpdatedAt(array_values($ids));

            $message = new BlogIndexingMessage(array_values($ids), $context);
            $message->addSkip(...static::getContainer()->get(BlogIndexer::class)->getOptions());
            static::getContainer()->get(BlogIndexer::class)->handle($message);

            $updatedAtById = $this->fetchUpdatedAt(array_values($ids));
            foreach ($ids as $id) {
                if (\in_array($id, $expectedUpdates[$case], true)) {
                    static::assertNotSame(self::UPDATED_AT_MARKER, $updatedAtById[$id], $case . ' must update its own scope');

                    continue;
                }

                static::assertSame(self::UPDATED_AT_MARKER, $updatedAtById[$id], $case . ' must not update another scope');
            }
        }
    }

    private function createBlog(Context $context): string
    {
        $id = Uuid::randomHex();
        $this->blogRepository()->create([[
            'id' => $id,
            'name' => 'Scoped blog ' . $id,
        ]], $context);

        return $id;
    }

    /**
     * @param list<string> $ids
     */
    private function resetUpdatedAt(array $ids): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE blog SET updated_at = :updatedAt WHERE id IN (:ids)',
            ['updatedAt' => self::UPDATED_AT_MARKER, 'ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function fetchUpdatedAt(array $ids): array
    {
        /** @var array<string, string> $updatedAtById */
        $updatedAtById = static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), DATE_FORMAT(updated_at, :format) FROM blog WHERE id IN (:ids)',
            ['format' => '%Y-%m-%d %H:%i:%s.%f', 'ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return $updatedAtById;
    }

    /**
     * @return EntityRepository<BlogCollection>
     */
    private function blogRepository(): EntityRepository
    {
        return static::getContainer()->get('blog.repository');
    }
}
