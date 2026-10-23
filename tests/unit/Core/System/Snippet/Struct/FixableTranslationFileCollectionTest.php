<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Struct\FixableTranslationFileCollection;
use Contena\Core\System\Snippet\Struct\TranslationFile;

/**
 * @internal
 */
#[CoversClass(FixableTranslationFileCollection::class)]
class FixableTranslationFileCollectionTest extends TestCase
{
    public function testAddGroupsFilesByTheirAgnosticPath(): void
    {
        $german = self::file('de-DE.json', 'de-DE', 'de');
        $austrian = self::file('de-AT.json', 'de-AT', 'de');

        $collection = new FixableTranslationFileCollection();
        $collection->add($german);
        $collection->add($austrian);

        static::assertCount(2, $collection);
        static::assertSame(
            ['path/to/file/frontend.de.json' => ['de-DE' => $german, 'de-AT' => $austrian]],
            $collection->getMapping()
        );
    }

    public function testSetGroupsTheFileByItsAgnosticPath(): void
    {
        $english = self::file('en-GB.json', 'en-GB', 'en');

        $collection = new FixableTranslationFileCollection();
        $collection->set('custom-key', $english);

        static::assertSame($english, $collection->get('custom-key'));
        static::assertSame(
            ['path/to/file/frontend.en.json' => ['en-GB' => $english]],
            $collection->getMapping()
        );
    }

    private static function file(string $filename, string $locale, string $language): TranslationFile
    {
        return new TranslationFile(
            filename: $filename,
            path: 'path/to/file',
            domain: 'frontend',
            locale: $locale,
            language: $language,
        );
    }
}
