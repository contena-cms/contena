<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Command;

use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiSchemaMigrationReporter;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[AsCommand(
    name: 'framework:channel-api:schema-migration-report',
    description: 'Reports Channel API component schemas grouped by JSON/PHP migration state.',
)]
class ChannelApiSchemaMigrationReportCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelApiSchemaMigrationReporter $reporter,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to the output file. "-" writes to stdout.')]
        string $outfile = '-',
        #[Option(description: 'Schema ownership scope to report.')]
        string $scope = CoreChannelApiSchemaMigrationScopeProvider::SCOPE,
        #[Option(description: 'Dumps the output in a human-readable form.', shortcut: 'p')]
        bool $pretty = false,
        #[Option(description: 'Returns a failure code when the report contains migration mismatches.')]
        bool $failOnMismatch = false,
    ): int {
        $supportedScopes = $this->reporter->getSupportedScopes();
        if (!\in_array($scope, $supportedScopes, true)) {
            $output->writeln(\sprintf(
                '<error>The scope option must be one of: %s.</error>',
                implode(', ', $supportedScopes),
            ));

            return self::FAILURE;
        }

        $report = $this->reporter->report($this->definitionRegistry->getDefinitions(), $scope);
        $jsonFlags = $pretty ? \JSON_PRETTY_PRINT : 0;
        $contents = json_encode($report, $jsonFlags | \JSON_THROW_ON_ERROR);

        $contents .= "\n";

        if ($outfile === '-') {
            $output->write($contents);
        } else {
            $this->filesystem->dumpFile($outfile, $contents);
        }

        if ($failOnMismatch && $report->hasMismatches()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
