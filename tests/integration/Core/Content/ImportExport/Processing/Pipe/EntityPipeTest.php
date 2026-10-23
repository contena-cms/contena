<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Processing\Pipe\EntityPipe;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class EntityPipeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityPipe(): void
    {
        $entityPipe = new EntityPipe(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            static::getContainer()->get(SerializerRegistry::class),
            null,
            null,
            static::getContainer()->get(PrimaryKeyResolver::class)
        );

        $sourceEntity = BlogDefinition::ENTITY_NAME;
        $config = new Config([], ['sourceEntity' => $sourceEntity], []);
        $id = Uuid::randomHex();

        $blog = (new BlogEntity())->assign([
            'id' => $id,
            'stock' => 101,
            'blogNumber' => 'P101',
            'active' => true,
            'translations' => new BlogTranslationCollection([
            ]),
        ]);
        $blog->setUniqueIdentifier($id);

        $result = iterator_to_array($entityPipe->in($config, $blog->jsonSerialize()));
        static::assertInstanceOf(BlogTranslationCollection::class, $blog->getTranslations());
        $first = $blog->getTranslations()->first();
        static::assertInstanceOf(BlogTranslationEntity::class, $first);
        $translation = $first->getName();
        static::assertIsString($translation);

        static::assertSame($id, $result['id']);
        static::assertSame($translation, $result['translations']['DEFAULT']['name']);
        static::assertSame((string) $blog->getStock(), $result['stock']);
        static::assertSame($blog->getBlogNumber(), $result['blogNumber']);
        static::assertSame('1', $result['active']);

        $result = iterator_to_array($entityPipe->out($config, $result));

        static::assertSame($blog->getId(), $result['id']);
        static::assertSame($translation, $result['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($blog->getStock(), $result['stock']);
        static::assertSame($blog->getBlogNumber(), $result['blogNumber']);
        static::assertSame($blog->getActive(), $result['active']);
    }
}
