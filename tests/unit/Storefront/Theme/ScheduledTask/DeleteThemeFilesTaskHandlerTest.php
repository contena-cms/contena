<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Contena\Storefront\Theme\UnusedThemeDirectoryDeleter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DeleteThemeFilesTaskHandler::class)]
class DeleteThemeFilesTaskHandlerTest extends TestCase
{
    public function testRunDelegatesToDeleter(): void
    {
        $unusedThemeFilesDeleter = $this->createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeFilesDeleter->expects($this->once())->method('deleteUnusedDirectories')->willReturn(0);

        $handler = new DeleteThemeFilesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $unusedThemeFilesDeleter
        );

        $handler->run();
    }
}
