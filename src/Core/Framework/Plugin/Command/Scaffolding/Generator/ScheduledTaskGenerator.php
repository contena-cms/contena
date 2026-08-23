<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
class ScheduledTaskGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const string OPTION_NAME = 'create-scheduled-task';
    private const string OPTION_DESCRIPTION = 'Create an example scheduled task';
    private const string CLI_QUESTION = 'Do you want to create an example scheduled task?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\ScheduledTask\ExampleTask::class)
        ->tag('contena.scheduled.task');

EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createScheduledTask($configuration));

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );
    }

    private function createScheduledTask(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/ScheduledTask/ExampleTask.php',
            self::STUB_DIRECTORY . '/scheduled-task.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
