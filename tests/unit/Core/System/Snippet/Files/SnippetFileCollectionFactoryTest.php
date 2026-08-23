<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Files\SnippetFileCollectionFactory;
use Contena\Core\System\Snippet\Files\SnippetFileLoaderInterface;
use Contena\Tests\Unit\Core\System\Snippet\Mock\MockSnippetFile;

/**
 * @internal
 */
#[CoversClass(SnippetFileCollectionFactory::class)]
class SnippetFileCollectionFactoryTest extends TestCase
{
    public function testCreateSnippetFileCollection(): void
    {
        $snippetFileLoaderMock = $this->createMock(SnippetFileLoaderInterface::class);
        $snippetFileLoaderMock->expects($this->once())
            ->method('loadSnippetFilesIntoCollection')
            ->willReturnCallback(static function (SnippetFileCollection $fileCollection): void {
                $fileCollection->add(new MockSnippetFile('frontend.de-DE', 'de-DE', '{}', true));
            });

        $factory = new SnippetFileCollectionFactory($snippetFileLoaderMock);

        $collection = $factory->createSnippetFileCollection();

        static::assertCount(1, $collection);
    }
}
