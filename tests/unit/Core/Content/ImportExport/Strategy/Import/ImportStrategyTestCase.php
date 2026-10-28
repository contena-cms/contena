<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\Strategy\Import;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
abstract class ImportStrategyTestCase extends TestCase
{
    protected EventDispatcherInterface&MockObject $eventDispatcher;

    /**
     * @var EntityRepository<MediaCollection>&MockObject
     */
    protected EntityRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
    }

    /**
     * @return \Generator<string, array{config: Config, method: 'create'|'update'|'upsert'}>
     */
    public static function importProvider(): \Generator
    {
        yield 'createEntities' => [
            'config' => new Config(
                mapping: [],
                parameters: [
                    'createEntities' => true,
                    'updateEntities' => false,
                ],
                updateBy: []
            ),
            'method' => 'create',
        ];

        yield 'updateEntities' => [
            'config' => new Config(
                mapping: [],
                parameters: [
                    'createEntities' => false,
                    'updateEntities' => true,
                ],
                updateBy: []
            ),
            'method' => 'update',
        ];

        yield 'upsertEntities' => [
            'config' => new Config(
                mapping: [],
                parameters: [
                    'createEntities' => true,
                    'updateEntities' => true,
                ],
                updateBy: []
            ),
            'method' => 'upsert',
        ];
    }
}
