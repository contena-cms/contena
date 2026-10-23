<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Commands;

use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Content\Sitemap\Service\SitemapChannelLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        private readonly SitemapChannelLoader $channelLoader,
        private readonly SitemapExporterInterface $sitemapExporter,
        private readonly AbstractChannelContextFactory $channelContextFactory
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('channelId', 'i', InputOption::VALUE_OPTIONAL, 'Generate sitemap only for for this sales channel')
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

        $channels = $this->channelLoader->loadChannels($context, $channelId);

        foreach ($channels as $channel) {
            foreach ($this->channelLoader->getLanguageIds($channel) as $languageId) {
                $channelContext = $this->channelContextFactory->create('', $channel->getId(), [ChannelContextService::LANGUAGE_ID => $languageId]);

                $output->writeln(\sprintf('Generating sitemaps for sales channel %s (%s) with and language %s...', $channel->getId(), $channel->getName() ?? '', $languageId));

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
}
