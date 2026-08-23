<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
class CustomFieldsetGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const string OPTION_NAME = 'create-custom-fieldset';
    private const string OPTION_DESCRIPTION = 'Create an example custom fieldset';
    private const string CLI_QUESTION = 'Do you want to create an example custom fieldset?';

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
