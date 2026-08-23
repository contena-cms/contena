<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Commands;

use Contena\Core\Content\Sitemap\Event\SitemapChannelCriteriaEvent;
use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'sitemap:generate',
    description: 'Generates sitemap files',
)]
class SitemapGenerateCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapChannelProvider $channelProvider,
        private readonly SitemapExporterInterface $sitemapExporter,
        private readonly AbstractChannelContextFactory $channelContextFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('channelId', 'i', InputOption::VALUE_OPTIONAL, 'Generate sitemap only for this channel')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force generation, even if generation has been locked by some other process'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $channelId = $input->getOption('channelId');

        $context = Context::createCLIContext();

        $criteria = $this->createCriteria($channelId);

        $this->eventDispatcher->dispatch(
            new SitemapChannelCriteriaEvent($criteria, $context)
        );

        foreach ($this->channelProvider->getChannels($criteria) as $channel) {
            $languageIds = $channel->getDomains()?->map(static fn (ChannelDomainEntity $channelDomain) => $channelDomain->getLanguageId()) ?? [];

            $languageIds = array_unique($languageIds);

            foreach ($languageIds as $languageId) {
                $channelContext = $this->channelContextFactory->create('', $channel->getId(), [ChannelContextService::LANGUAGE_ID => $languageId]);

                $output->writeln(\sprintf('Generating sitemaps for channel %s (%s) with language %s...', $channel->getId(), $channel->getName() ?? '', $languageId));

                try {
                    $this->generateSitemap($channelContext, $input->getOption('force'));
                } catch (AlreadyLockedException $exception) {
                    $output->writeln(\sprintf('ERROR: %s', $exception->getMessage()));
                }
            }
        }

        $output->writeln('done!');

        return self::SUCCESS;
    }

    private function generateSitemap(ChannelContext $channelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->sitemapExporter->generate($channelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($channelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }

    private function createCriteria(?string $channelId = null): Criteria
    {
        $criteria = $channelId ? new Criteria([$channelId]) : new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotEqualsFilter('domains.id', null));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::CHANNEL_TYPE_WEB));

        return $criteria;
    }
}
