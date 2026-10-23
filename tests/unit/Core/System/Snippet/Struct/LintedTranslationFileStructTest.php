<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Snippet\Struct\LintedTranslationFileStruct;
use Contena\Core\System\Snippet\Struct\TranslationFile;
use Contena\Core\System\Snippet\Struct\TranslationFileCollection;

/**
 * @internal
 */
#[CoversClass(LintedTranslationFileStruct::class)]
class LintedTranslationFileStructTest extends TestCase
{
    public function testGetDomainCollectionTreatsCustomDomainsAsFrontend(): void
    {
        $frontend = self::file('de-DE.json', 'frontend');
        $custom = self::file('de-DE.json', 'swag-cms-extensions');
        $messages = self::file('de-DE.base.json', 'messages');

        $struct = new LintedTranslationFileStruct(new TranslationFileCollection([$frontend, $custom, $messages]));

        static::assertSame(
            [$frontend, $custom],
            array_values($struct->getDomainCollection('frontend')->getElements())
        );
        static::assertSame(
            [$messages],
            array_values($struct->getDomainCollection('messages')->getElements())
        );
    }

    public function testCompleteAndSpecificCollectionsReturnTheConstructorArguments(): void
    {
        $complete = new TranslationFileCollection([self::file('de-DE.json', 'frontend')]);
        $specific = new TranslationFileCollection([self::file('de-AT.json', 'frontend')]);

        $struct = new LintedTranslationFileStruct($complete, $specific);

        static::assertSame($complete, $struct->getCompleteCollection());
        static::assertSame($specific, $struct->getSpecificCollection());
    }

    public function testFixableAndFixingCollectionsCollectAddedFiles(): void
    {
        $struct = new LintedTranslationFileStruct();

        $fixable = self::file('de.json', 'frontend');
        $fixed = self::file('en.json', 'frontend');

        $struct->addFixableFile($fixable);
        $struct->addToFixingCollection($fixed);

        static::assertSame([$fixable], array_values($struct->getFixableFiles()->getElements()));
        static::assertSame([$fixed], array_values($struct->getFixingCollection()->getElements()));
    }

    private static function file(string $filename, string $domain): TranslationFile
    {
        return new TranslationFile(
            filename: $filename,
            path: 'path/to/file',
            domain: $domain,
            locale: 'de-DE',
            language: 'de',
        );
    }
}
