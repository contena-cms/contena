<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesTaskHandler::class)]
class DeleteThemeFilesTaskHandlerTest extends TestCase
{
    public function testRunDelegatesToDeleter(): void
    {
        $unusedThemeDirectoryDeleter = $this->createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())->method('deleteUnusedDirectories')->willReturn(0);

        $handler = new DeleteThemeFilesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $unusedThemeDirectoryDeleter
        );

        $handler->run();
    }
}
