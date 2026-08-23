<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
class FrontendControllerGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const string OPTION_NAME = 'create-frontend-controller';
    private const string OPTION_DESCRIPTION = 'Create an example Frontend controller';
    private const string CLI_QUESTION = 'Do you want to create an example Frontend controller?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\Frontend\Controller\ExampleController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

EOL;

    private string $routesPhpEntry = <<<'EOL'

    $routes->import('../../Frontend/Controller/**/*Controller.php', 'attribute');

EOL;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);

            return;
        }

        if ($this->shouldAskCliQuestion && $io->confirm(self::CLI_QUESTION)) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);
        }
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createController($configuration));
        $stubCollection->add($this->createTemplate());

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace('{{ namespace }}', $configuration->namespace, $this->servicesPhpEntry)
        );

        $stubCollection->append('src/Resources/config/routes.php', $this->routesPhpEntry);
    }

    private function createController(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Frontend/Controller/ExampleController.php',
            self::STUB_DIRECTORY . '/frontend-controller.stub',
            [
                'namespace' => $configuration->namespace,
                'className' => $configuration->name,
            ]
        );
    }

    private function createTemplate(): Stub
    {
        return Stub::template(
            'src/Resources/views/frontend/page/example.html.twig',
            self::STUB_DIRECTORY . '/frontend-template.stub'
        );
    }
}
