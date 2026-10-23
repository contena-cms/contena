<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Contena\Core\Content\ImportExport\Event\Subscriber\BlogCriteriaSubscriber;
use Contena\Core\Content\ImportExport\ImportExportProfileEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[CoversClass(BlogCriteriaSubscriber::class)]
class BlogCriteriaSubscriberTest extends TestCase
{
    /**
     * @param list<array{key: string, mappedKey: string}> $mapping
     * @param list<string> $expectedExcluded
     */
    #[DataProvider('mappingProvider')]
    public function testExcludesDescriptionWhenNotMapped(array $mapping, array $expectedExcluded): void
    {
        $criteria = new Criteria();
        $this->enrich($criteria, $mapping);

        static::assertSame($expectedExcluded, $criteria->getExcludedFields());
    }

    /**
     * @return iterable<string, array{list<array{key: string, mappedKey: string}>, list<string>}>
     */
    public static function mappingProvider(): iterable
    {
        yield 'description not mapped -> excluded' => [
            [['key' => 'blogNumber', 'mappedKey' => 'blog_number'], ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name']],
            ['description'],
        ];
        yield 'only an unrelated field mapped -> description excluded' => [
            [['key' => 'customFields.gtin', 'mappedKey' => 'gtin']],
            ['description'],
        ];
        yield 'description mapped -> kept' => [
            [['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description']],
            [],
        ];
        yield 'description mapped in a non-default language -> kept' => [
            [['key' => 'translations.de-DE.description', 'mappedKey' => 'description']],
            [],
        ];
    }

    public function testExcludesDescriptionOnTranslationsAssociationWhenLoaded(): void
    {
        $criteria = new Criteria();
        // the mapping references a translated field, so the export loads the translations association
        $criteria->addAssociation('translations');

        $this->enrich($criteria, [['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name']]);

        static::assertSame(['description'], $criteria->getExcludedFields());
        static::assertSame(['description'], $criteria->getAssociation('translations')->getExcludedFields());
    }

    public function testDoesNotAddTranslationsAssociationJustToReduceIt(): void
    {
        $criteria = new Criteria();

        $this->enrich($criteria, [['key' => 'blogNumber', 'mappedKey' => 'blog_number']]);

        static::assertFalse($criteria->hasAssociation('translations'));
        static::assertSame(['description'], $criteria->getExcludedFields());
    }

    public function testSkipsNonBlogProfiles(): void
    {
        $criteria = new Criteria();

        $this->enrich($criteria, [['key' => 'blogNumber', 'mappedKey' => 'blog_number']], 'category');

        static::assertSame([], $criteria->getExcludedFields());
    }

    /**
     * @param list<array{key: string, mappedKey: string}> $mapping
     */
    private function enrich(Criteria $criteria, array $mapping, string $sourceEntity = BlogDefinition::ENTITY_NAME): void
    {
        $profile = new ImportExportProfileEntity();
        $profile->setSourceEntity($sourceEntity);

        $log = new ImportExportLogEntity();
        $log->setId('log-id');
        $log->setProfile($profile);
        $log->setConfig(['mapping' => $mapping, 'parameters' => ['includeVariants' => true]]);

        $event = new EnrichExportCriteriaEvent($criteria, $log, Context::createDefaultContext());

        (new BlogCriteriaSubscriber())->enrich($event);
    }
}
