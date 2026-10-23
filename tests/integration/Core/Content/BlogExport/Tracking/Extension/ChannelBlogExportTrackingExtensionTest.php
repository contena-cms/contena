<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Tracking\Extension;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @internal
 */
class ChannelBlogExportTrackingExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testChannelDefinitionContainsTrackingAssociations(): void
    {
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->getByEntityName(ChannelDefinition::ENTITY_NAME);
        static::assertInstanceOf(ChannelDefinition::class, $definition);

        $fields = $definition->getFields();

        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('channelTrackingOrders'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('channelTrackingUsers'));
    }
}
