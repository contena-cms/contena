<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\ChannelCollection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'sales-channel:file:enable',
    description: 'Enable llms.txt and AGENTS.md for Frontend and Headless sales channels',
)]
class ChannelFileEnableCommand extends Command
{
    private const FILE_NAMES = ['llms.txt', 'AGENTS.md'];

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<ChannelFileCollection> $channelFileRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $channelFileRepository,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $ids
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Which Sales Channels do you want to enable the files for? (Optional when --all flag is used)')]
        array $ids = [],
        #[Option(description: 'Enable the files for all Frontend and Headless sales channels', shortcut: 'a')]
        bool $all = false,
    ): int {
        if ($all === false && $ids === []) {
            $output->write('No files were enabled. Provide id(s) or run with --all option.');

            return self::SUCCESS;
        }

        $context = Context::createCLIContext();
        $requestedIds = $all ? null : $this->validIds($ids, $output);
        $channelIds = $this->findChannelIds($requestedIds, $context);

        $unmatchedIds = array_values(array_diff($requestedIds ?? [], $channelIds));
        if ($unmatchedIds !== []) {
            $output->writeln(\sprintf('Ignored id(s) that match no Frontend or Headless sales channel: %s', implode(', ', $unmatchedIds)));
        }

        if ($channelIds === []) {
            $output->write('No files were enabled. No Frontend or Headless sales channel matches.');

            return self::SUCCESS;
        }

        $missingFiles = $this->missingFiles($channelIds, $context);

        if ($missingFiles === []) {
            $output->write('No files were enabled. The files already exist for every selected sales channel.');

            return self::SUCCESS;
        }

        $this->channelFileRepository->create($missingFiles, $context);

        $output->write(\sprintf(
            'Enabled %d file(s) for %d sales channel(s)',
            \count($missingFiles),
            \count(array_unique(array_column($missingFiles, 'channelId'))),
        ));

        return self::SUCCESS;
    }

    /**
     * The DAL only matches lowercase ids, and an invalid id would abort the search with an exception.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function validIds(array $ids, OutputInterface $output): array
    {
        $validIds = [];
        $invalidIds = [];
        foreach ($ids as $id) {
            $id = strtolower($id);

            if (Uuid::isValid($id)) {
                $validIds[$id] = $id;

                continue;
            }

            $invalidIds[$id] = $id;
        }

        if ($invalidIds !== []) {
            $output->writeln(\sprintf('Ignored invalid id(s): %s', implode(', ', $invalidIds)));
        }

        return array_values($validIds);
    }

    /**
     * @param list<string>|null $ids
     *
     * @return list<string>
     */
    private function findChannelIds(?array $ids, Context $context): array
    {
        // An empty id list would not restrict the criteria and select every sales channel.
        if ($ids === []) {
            return [];
        }

        $criteria = new Criteria($ids);
        $criteria->addFilter(new EqualsAnyFilter('typeId', [
            Defaults::CHANNEL_TYPE_FRONTEND,
            Defaults::CHANNEL_TYPE_API,
        ]));

        return array_values(array_filter(
            $this->channelRepository->searchIds($criteria, $context)->getIds(),
            \is_string(...),
        ));
    }

    /**
     * Existing rows are left untouched, so a file the merchant switched off stays off.
     *
     * @param list<string> $channelIds
     *
     * @return list<array{channelId: string, fileFamily: string, fileName: string, enabled: true}>
     */
    private function missingFiles(array $channelIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('channelId', $channelIds),
            new EqualsFilter('fileFamily', ChannelFile::DEFAULT_FILE_FAMILY),
        );

        $existing = [];
        foreach ($this->channelFileRepository->search($criteria, $context)->getEntities() as $file) {
            // File names are matched case-insensitively, so a legacy `agents.md` row counts as `AGENTS.md`.
            $existing[$file->getChannelId()][mb_strtolower($file->getFileName())] = true;
        }

        $missingFiles = [];
        foreach ($channelIds as $channelId) {
            foreach (self::FILE_NAMES as $fileName) {
                if (isset($existing[$channelId][mb_strtolower($fileName)])) {
                    continue;
                }

                $missingFiles[] = [
                    'channelId' => $channelId,
                    'fileFamily' => ChannelFile::DEFAULT_FILE_FAMILY,
                    'fileName' => $fileName,
                    'enabled' => true,
                ];
            }
        }

        return $missingFiles;
    }
}
