<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchFieldMapper::class)]
class ElasticsearchFieldMapperTest extends TestCase
{
    public function testMapTranslatedField(): void
    {
        $items = [['name' => 'foo', 'languageId' => 'de-DE'], ['name' => null, 'languageId' => 'en-GB']];
        $fallbackItems = [['name' => 'foo-baz', 'languageId' => 'de-DE'], ['name' => 'bar', 'languageId' => 'en-GB'], ['name' => 'baz'], ['name' => 'foo VI', 'languageId' => 'vi-VN']];
        $fieldValue = ElasticsearchFieldMapper::translated('name', $items, $fallbackItems);

        static::assertEquals(['de-DE' => 'foo', 'en-GB' => 'bar', 'vi-VN' => 'foo VI', Defaults::LANGUAGE_SYSTEM => 'baz'], $fieldValue);
    }

    public function testMapToManyAssociations(): void
    {
        $items = [
            ['id' => 'fooId', 'name' => 'foo in EN', 'languageId' => 'en-GB'],
            ['id' => 'fooId', 'name' => 'foo in DE', 'languageId' => 'de-DE'],
            ['id' => 'barId', 'name' => 'bar', 'description' => 'bar description', 'languageId' => 'en-GB'],
        ];

        $fieldValue = ElasticsearchFieldMapper::toManyAssociations($items, ['name', 'description']);

        static::assertEquals([
            [
                'id' => 'fooId',
                '_count' => 1,
                'name' => [
                    'en-GB' => 'foo in EN',
                    'de-DE' => 'foo in DE',
                ],
                'description' => [
                    'en-GB' => null,
                ],
            ], [
                'id' => 'barId',
                '_count' => 1,
                'name' => ['en-GB' => 'bar'],
                'description' => [
                    'en-GB' => 'bar description',
                ],
            ],
        ], $fieldValue);
    }

    public function testMapCustomFields(): void
    {
        $deLanguageId = Uuid::randomHex();
        $enLanguageId = Uuid::randomHex();

        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag(['elasticsearch.blog.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
            'cf_bar' => 'text',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            'cf_bool' => 'bool',
            'cf_text' => 'text',
        ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $mapper = new ElasticsearchFieldMapper($utils);

        $formatted = $mapper->customFields(BlogDefinition::ENTITY_NAME, [
            $deLanguageId => [
                'cf_foo' => 'danke',
                'cf_baz' => '234',
                'cf_bool' => 0,
                'cf_text' => 'text',
                'cf_bar' => '123E321',
            ],
            $enLanguageId => [
                'cf_foo' => 'thankyou',
                'cf_baz' => '123',
                'cf_bool' => 'true',
                'cf_text' => '10.0',
                'cf_bar' => '123E321',
            ],
        ], new Context(new SystemSource()));

        /**
         * Specifically check, that this case does not happen anymore:
         * https://github.com/contena/contena/issues/4459 (comments)
         **/
        static::assertNotSame($formatted[$deLanguageId]['cf_bar'], \INF);
        static::assertNotSame($formatted[$enLanguageId]['cf_bar'], \INF);

        static::assertEquals([
            $deLanguageId => [
                'cf_foo' => 'danke',
                'cf_baz' => 234.0,
                'cf_bool' => false,
                'cf_text' => 'text',
                'cf_bar' => '123E321',
            ],
            $enLanguageId => [
                'cf_foo' => 'thankyou',
                'cf_baz' => 123.0,
                'cf_bool' => true,
                'cf_text' => '10.0',
                'cf_bar' => '123E321',
            ],
        ], $formatted);
    }
}
