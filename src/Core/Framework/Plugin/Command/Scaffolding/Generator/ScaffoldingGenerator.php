<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
interface ScaffoldingGenerator
{
    public const string STUB_DIRECTORY = __DIR__ . '/../stubs';

    public function hasCommandOption(): bool;

    public function getCommandOptionName(): string;

    public function getCommandOptionDescription(): string;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void;

    public function generateStubs(PluginScaffoldConfiguration $configuration, StubCollection $stubCollection): void;
}
