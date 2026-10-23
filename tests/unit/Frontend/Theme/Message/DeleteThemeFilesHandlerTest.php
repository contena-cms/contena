<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Message;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\Message\DeleteThemeFilesHandler;
use Contena\Frontend\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesHandler::class)]
class DeleteThemeFilesHandlerTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFilesAreDeletedIfPathIsCurrentlyNotActive(): void
    {
        $currentPath = 'path';

        $message = new DeleteThemeFilesMessage($currentPath, 'channel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())->method('deleteDirectory')->with('theme' . \DIRECTORY_SEPARATOR . $currentPath);

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            // the path builder will generate a different path then the hard coded one
            new MD5ThemePathBuilder(),
        );

        $handler($message);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFilesAreNotDeletedIfPathIsCurrentlyActive(): void
    {
        $pathBuilder = new MD5ThemePathBuilder();

        $currentPath = $pathBuilder->assemblePath('channel', 'theme');

        $message = new DeleteThemeFilesMessage($currentPath, 'channel', 'theme');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->never())->method('deleteDirectory');

        $handler = new DeleteThemeFilesHandler(
            $filesystem,
            $pathBuilder,
        );

        $handler($message);
    }
}
