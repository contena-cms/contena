<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    name: 'channel:maintenance:enable',
    description: 'Enable maintenance mode for a channel',
)]
class ChannelMaintenanceEnableCommand extends Command
{
    protected bool $setMaintenanceMode = true;

    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
    ) {
        parent::__construct();
    }

    /**
     * @param list<string> $ids
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Which channels do you want to update maintenance mode for? (Optional when --all flag is used)')]
        array $ids = [],
        #[Option(description: 'Set maintenance mode for all channels', shortcut: 'a')]
        bool $all = false,
    ): int {
        $context = Context::createCLIContext();
        $criteria = new Criteria();

        if ($all === false) {
            if ($ids === []) {
                $output->write('No channels were updated. Provide id(s) or run with --all option.');

                return self::SUCCESS;
            }

            $criteria->setIds($ids);
        }

        $channels = $this->channelRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($channels === []) {
            $output->write('No channels were updated');

            return self::SUCCESS;
        }

        foreach ($channels as &$channel) {
            $channel['maintenance'] = $this->setMaintenanceMode;
        }
        unset($channel);

        $this->channelRepository->update($channels, $context);
        $output->write(\sprintf('Updated maintenance mode for %d channel(s)', \count($channels)));

        return self::SUCCESS;
    }
}
