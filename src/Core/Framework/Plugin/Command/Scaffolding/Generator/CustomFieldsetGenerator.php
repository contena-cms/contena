<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldsetGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-custom-fieldset';
    private const OPTION_TITLE = 'Custom Fieldset';
    private const OPTION_DESCRIPTION = 'Create an example custom fieldset';
    private const OPTION_DESCRIPTION_LONG = 'Custom fields are additional data fields that extend existing Contena entities without requiring a completely custom entity. Use them when you need to store simple, scalar values such as text, numbers, or selections; for entity relationships and complex / performance critical cases, use a custom entity instead, which gives you full control over the DB schema.';
    private const CLI_QUESTION = 'Do you want to create an example custom fieldset?';

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add(Stub::template(
            'src/Resources/config/custom-fields.xml',
            self::STUB_DIRECTORY . '/custom-fields-xml.stub'
        ));
    }
}
