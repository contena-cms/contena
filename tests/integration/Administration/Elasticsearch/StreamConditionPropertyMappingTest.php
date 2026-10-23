<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Elasticsearch;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;

/**
 * @internal
 */
class StreamConditionPropertyMappingTest extends TestCase
{
    use KernelTestBehaviour;

    private ChannelBlogDefinition $blogDefinition;

    private AbstractElasticsearchDefinition $elasticDefinition;

    protected function setUp(): void
    {
        $this->blogDefinition = static::getContainer()->get(ChannelBlogDefinition::class);

        $this->elasticDefinition = static::getContainer()->get(ElasticsearchBlogDefinition::class);
    }

    public function testMappingHasConditionField(): void
    {
        $js = file_get_contents(__DIR__ . '/../../../../src/Administration/Resources/app/administration/src/app/service/blog-stream-condition.service.js');

        if (!$js) {
            static::fail('blog-stream-condition.service.js not found');
        }

        $regex = '/blog:(.*?)\[(.*?)]/s';
        preg_match($regex, $js, $matches);

        if ($matches === [] || $matches[2] === '') {
            static::fail('could not find blog properties in blog-stream-condition.service.js');
        }

        $json = \sprintf('[%s]', rtrim(trim(str_replace(['\'', \PHP_EOL], ['"', ''], $matches[2])), ','));
        $properties = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($properties)) {
            static::fail('could not extract blog properties from blog-stream-condition.service.js');
        }

        $mapping = $this->elasticDefinition->getMapping(Context::createDefaultContext());
        $mappedProperties = array_keys($mapping['properties']);

        $unmappedProperties = array_filter($properties, function (string $property) use ($mappedProperties): bool {
            $field = $this->blogDefinition->getField($property);

            if ($field instanceof AssociationField || $field instanceof CheapestPriceField || $field instanceof PriceField) {
                return false;
            }

            return !\in_array($property, $mappedProperties, true);
        });

        static::assertCount(
            0,
            $unmappedProperties,
            \sprintf(
                'The following blog fields available for filters in blog streams are not mapped for elasticsearch: %s',
                implode(', ', $unmappedProperties)
            )
        );
    }
}
