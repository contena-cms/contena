<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class EntityDefinitionHasSinceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllDefinitionsHasSince(): void
    {
        $service = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $definitionsWithoutSince = [];

        foreach ($service->getDefinitions() as $definition) {
            if ($definition instanceof AttributeMappingDefinition || $definition instanceof AttributeTranslationDefinition) {
                continue;
            }

            if ($definition->since() === null) {
                $definitionsWithoutSince[] = $definition->getEntityName();
            }
        }

        static::assertCount(0, $definitionsWithoutSince, \sprintf('Following definitions does not have a since version: %s', implode(',', $definitionsWithoutSince)));
    }
}
