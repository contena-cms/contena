<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;

/**
 * @internal
 */
#[CoversClass(EntityDeleteTool::class)]
class EntityDeleteToolTest extends TestCase
{
    public function testDeniesAccessWithoutDeletePermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['blog:read']);
        $context = new Context($source, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new EntityDeleteTool($registry, $contextProvider, static::createStub(Connection::class));
        $result = $this->decode(($tool)('blog', '["abc123"]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('blog:delete', $result['error']);
    }

    public function testReturnsErrorWhenEntityNotFound(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new EntityDeleteTool($registry, $contextProvider, static::createStub(Connection::class));
        $result = $this->decode(($tool)('unknown_entity', '["abc"]'));

        static::assertFalse($result['success']);
        static::assertStringContainsString('unknown_entity', $result['error']);
    }

    public function testParsesCommaSeparatedIds(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with(static::callback(function (array $payload): bool {
                return $payload === [['id' => 'id1'], ['id' => 'id2'], ['id' => 'id3']];
            }));

        $tool = $this->createTool($repository);
        $result = $this->decode(($tool)('blog', 'id1, id2, id3', false));

        static::assertTrue($result['success']);
    }

    public function testReturnsErrorForEmptyIds(): void
    {
        $tool = $this->createTool();
        $result = $this->decode(($tool)('blog', '[]'));

        static::assertFalse($result['success']);
        static::assertSame('No valid IDs provided.', $result['error']);
    }

    public function testReturnsErrorForBlankCommaString(): void
    {
        $tool = $this->createTool();
        $result = $this->decode(($tool)('blog', ', , '));

        static::assertFalse($result['success']);
        static::assertSame('No valid IDs provided.', $result['error']);
    }

    public function testDryRunRollsBackAndReturnsDeleteResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $writeResult = new EntityWriteResult('abc', [], 'blog', EntityWriteResult::OPERATION_DELETE);
        $writtenEvent = new EntityWrittenEvent('blog', [$writeResult], Context::createDefaultContext());
        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection([$writtenEvent]));

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('blog', '["abc"]', true));

        static::assertTrue($result['success']);
        static::assertTrue($result['_meta']['dryRun']);
        static::assertSame('blog', $result['data'][0]['entity']);
        static::assertSame(['abc'], $result['data'][0]['ids']);
    }

    public function testDryRunReturnsErrorWhenDeleteThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willThrowException(new \RuntimeException('FK constraint'));

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('blog', '["abc"]', true));

        static::assertFalse($result['success']);
        static::assertSame('FK constraint', $result['error']);
    }

    public function testRealDeleteDoesNotRollBack(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');
        $connection->expects($this->never())->method('rollBack');

        $events = static::createStub(EntityWrittenContainerEvent::class);
        $events->method('getEvents')->willReturn(new NestedEventCollection());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('delete')->willReturn($events);

        $tool = $this->createTool($repository, $connection);
        $result = $this->decode(($tool)('blog', '["abc123"]', false));

        static::assertTrue($result['success']);
        static::assertFalse($result['_meta']['dryRun']);
    }

    /**
     * @param (MockObject&EntityRepository<EntityCollection<Entity>>)|null $repository
     */
    private function createTool(?EntityRepository $repository = null, ?Connection $connection = null): EntityDeleteTool
    {
        if ($repository === null) {
            $repository = static::createStub(EntityRepository::class);
            $events = static::createStub(EntityWrittenContainerEvent::class);
            $events->method('getEvents')->willReturn(new NestedEventCollection());
            $repository->method('delete')->willReturn($events);
        }

        $connection ??= static::createStub(Connection::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new EntityDeleteTool($registry, $contextProvider, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
