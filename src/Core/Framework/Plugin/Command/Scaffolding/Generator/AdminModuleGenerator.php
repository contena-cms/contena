<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
class AdminModuleGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const string OPTION_NAME = 'create-admin-module';
    private const string OPTION_DESCRIPTION = 'Create an example admin module';
    private const string CLI_QUESTION = 'Do you want to create an example admin module?';

    private string $mainJsEntry = <<<'EOL'
    // Import admin module
    import './module/ct-example';

    EOL;

    private string $snippet = <<<'EOL'
    {
        "ct-example": {
            "general": {
                "mainMenuItemGeneral": "My custom module",
                "descriptionTextModule": "Manage this custom module here"
            }
        }
    }

    EOL;

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createModule());
        $stubCollection->add($this->createMainJsEntry());

        foreach ($this->createSnippets() as $snippet) {
            $stubCollection->add($snippet);
        }
    }

    private function createModule(): Stub
    {
        return Stub::template(
            'src/Resources/app/administration/src/module/ct-example/index.js',
            self::STUB_DIRECTORY . '/js-module.stub'
        );
    }

    private function createMainJsEntry(): Stub
    {
        return Stub::raw(
            'src/Resources/app/administration/src/main.js',
            $this->mainJsEntry,
        );
    }

    /**
     * @return Stub[]
     */
    private function createSnippets(): array
    {
        return [
            Stub::raw(
                'src/Resources/app/administration/src/snippet/en.json',
                $this->snippet
            ),
            Stub::raw(
                'src/Resources/app/administration/src/snippet/zh.json',
                $this->snippet
            ),
        ];
    }
}
