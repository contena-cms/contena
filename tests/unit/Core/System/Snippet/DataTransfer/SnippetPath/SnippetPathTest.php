<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\DataTransfer\SnippetPath;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;

/**
 * @internal
 */
#[CoversClass(SnippetPath::class)]
class SnippetPathTest extends TestCase
{
    public function testLocalCanBeSet(): void
    {
        $snippetPathLocal = new SnippetPath('path/to/snippet', true);
        $snippetPathNonLocal = new SnippetPath('path/to/snippet', false);
        static::assertTrue($snippetPathLocal->isLocal);
        static::assertFalse($snippetPathNonLocal->isLocal);
    }
}
