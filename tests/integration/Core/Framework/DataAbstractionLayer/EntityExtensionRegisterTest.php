<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogManufacturerDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogManufacturerExtension;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
class EntityExtensionRegisterTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
        parent::tearDown();
    }

    public function testAddEntityExtensionToEntityWhichAlsoHasChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedBlogDefinition::class);
        $this->registerDefinitionWithExtensions(BlogDefinition::class, BlogExtension::class);

        $fields = static::getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(BlogDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerChannelDefinition(ExtendedBlogDefinition::class);
        $this->registerChannelDefinitionWithExtensions(BlogDefinition::class, BlogExtension::class);
        $fields = static::getContainer()
            ->get(ChannelDefinitionInstanceRegistry::class)
            ->get(BlogDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }

    public function testAddEntityExtensionToEntityWhichDoesNotHasChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedBlogManufacturerDefinition::class);
        $this->registerDefinitionWithExtensions(BlogManufacturerDefinition::class, BlogManufacturerExtension::class);

        $fields = static::getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(BlogManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerChannelDefinition(ExtendedBlogManufacturerDefinition::class);
        $this->registerChannelDefinitionWithExtensions(BlogManufacturerDefinition::class, BlogManufacturerExtension::class);
        $fields = static::getContainer()
            ->get(ChannelDefinitionInstanceRegistry::class)
            ->get(BlogManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }
}
