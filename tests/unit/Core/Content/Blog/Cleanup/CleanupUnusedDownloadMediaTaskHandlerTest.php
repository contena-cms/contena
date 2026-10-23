<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Content\Blog\Aggregate\BlogDownload\BlogDownloadDefinition;
use Contena\Core\Content\Blog\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CleanupUnusedDownloadMediaTaskHandler::class)]
class CleanupUnusedDownloadMediaTaskHandlerTest extends TestCase
{
    private MockObject&UnusedMediaPurger $purger;

    private CleanupUnusedDownloadMediaTaskHandler $handler;

    protected function setUp(): void
    {
        $this->purger = $this->createMock(UnusedMediaPurger::class);

        $this->handler = new CleanupUnusedDownloadMediaTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->purger
        );
    }

    public function testRun(): void
    {
        $this->purger
            ->expects($this->once())
            ->method('deleteNotUsedMedia')
            ->with(null, null, null, BlogDownloadDefinition::ENTITY_NAME);

        $this->handler->run();
    }
}
