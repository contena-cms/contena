<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog\Command;

use Contena\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Streams the consent log to stdout for compliance exports, e.g.
 * `bin/console cookie:consent:export --from=2026-01-01 --to=2026-07-01 --format=csv > consents.csv`.
 *
 * @internal
 */
#[AsCommand(
    name: 'cookie:consent:export',
    description: 'Export the recorded cookie consent decisions as JSON or CSV',
)]
class ExportCookieConsentLogCommand extends Command
{
    private const string FORMAT_JSON = 'json';

    private const string FORMAT_CSV = 'csv';

    private const array CSV_COLUMNS = ['dataScopeId', 'consentId', 'createdAt', 'consentAction', 'channelId', 'languageId', 'configHash', 'groupDecisions', 'acceptedCookies'];

    public function __construct(private readonly AbstractCookieConsentLogStorage $storage)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Only decisions recorded at or after this date/time (inclusive)', '1970-01-01')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Only decisions recorded before this date/time (exclusive), defaults to now')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Only decisions of this channel id')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: json (one array) or csv', self::FORMAT_JSON);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);

        $format = $input->getOption('format');
        if (!\in_array($format, [self::FORMAT_JSON, self::FORMAT_CSV], true)) {
            $io->error(\sprintf('Unknown format "%s", expected "json" or "csv"', $format));

            return self::INVALID;
        }

        $channelId = $input->getOption('channel');
        if ($channelId !== null && !Uuid::isValid($channelId)) {
            $io->error(\sprintf('"%s" is not a valid channel id', $channelId));

            return self::INVALID;
        }

        try {
            $from = new \DateTimeImmutable((string) $input->getOption('from'));
            $to = new \DateTimeImmutable((string) ($input->getOption('to') ?? 'now'));
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());

            return self::INVALID;
        }

        $records = $this->storage->iterate($from, $to, $channelId);

        if ($format === self::FORMAT_CSV) {
            $this->writeCsv($output, $records);
        } else {
            $this->writeJson($output, $records);
        }

        return self::SUCCESS;
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     */
    private function writeJson(OutputInterface $output, iterable $records): void
    {
        $output->write('[');

        $first = true;
        foreach ($records as $record) {
            $output->write(($first ? '' : ',') . json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
            $first = false;
        }

        $output->writeln(']');
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     */
    private function writeCsv(OutputInterface $output, iterable $records): void
    {
        $output->writeln($this->csvLine(self::CSV_COLUMNS));

        foreach ($records as $record) {
            $data = $record->jsonSerialize();
            $data['groupDecisions'] = json_encode($data['groupDecisions'], \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT);
            $data['acceptedCookies'] = json_encode($data['acceptedCookies'], \JSON_THROW_ON_ERROR);

            $output->writeln($this->csvLine(array_map(static fn (string $column) => $data[$column], self::CSV_COLUMNS)));
        }
    }

    /**
     * @param list<mixed> $fields
     */
    private function csvLine(array $fields): string
    {
        $handle = fopen('php://memory', 'r+');
        \assert($handle !== false);

        fputcsv($handle, $fields, escape: '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return rtrim($line, "\n");
    }
}
