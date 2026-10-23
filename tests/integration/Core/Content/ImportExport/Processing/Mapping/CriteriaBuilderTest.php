<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\Processing\Mapping;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\Processing\Mapping\CriteriaBuilder;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class CriteriaBuilderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoAssociations(): void
    {
        $criteriaBuild = new CriteriaBuilder(static::getContainer()->get(BlogDefinition::class));

        $criteria = new Criteria();
        $config = new Config(
            [
                'name',
            ],
            [],
            []
        );
        $criteriaBuild->enrichCriteria($config, $criteria);

        static::assertCount(0, $criteria->getAssociations());
    }

    public function testAssociations(): void
    {
        $criteriaBuild = new CriteriaBuilder(static::getContainer()->get(BlogDefinition::class));

        $criteria = new Criteria();
        $config = new Config(
            [
                'name',
                'translations.name',
                'visibilities.search',
                'manufacturer.media.translations.title',
            ],
            [],
            []
        );
        $criteriaBuild->enrichCriteria($config, $criteria);

        $associations = $criteria->getAssociations();
        static::assertNotCount(0, $associations);

        static::assertArrayHasKey('translations', $associations);
        static::assertArrayHasKey('visibilities', $associations);

        static::assertArrayHasKey('manufacturer', $associations);
        $manufacturerAssociations = $associations['manufacturer']->getAssociations();
        static::assertArrayHasKey('media', $manufacturerAssociations);

        static::assertInstanceOf(Criteria::class, $manufacturerAssociations['media']);
        $manufacturerMediaAssociations = $manufacturerAssociations['media']->getAssociations();
        static::assertArrayHasKey('translations', $manufacturerMediaAssociations);
        static::assertInstanceOf(Criteria::class, $manufacturerMediaAssociations['translations']);
    }
}
