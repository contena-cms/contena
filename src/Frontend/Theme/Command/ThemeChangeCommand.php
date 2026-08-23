<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeService;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'theme:change',
    description: 'Change the active theme for a channel',
)]
class ThemeChangeCommand extends Command
{
    private readonly Context $context;

    private SymfonyStyle $io;

    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly FrontendPluginRegistry $pluginRegistry,
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $themeRepository,
        private readonly UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter
    ) {
        parent::__construct();
        $this->context = Context::createCLIContext();
    }

    protected function configure(): void
    {
        $this->addArgument('theme-name', InputArgument::OPTIONAL, 'Technical theme name');
        $this->addOption('channel', 'c', InputOption::VALUE_REQUIRED, 'Channel ID. Can not be used together with --all.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Set theme for all channels. Can not be used together with -c.');
        $this->addOption('no-compile', null, InputOption::VALUE_NONE, 'Skip theme compiling');
        $this->addOption('sync', null, InputOption::VALUE_NONE, 'Compile the theme synchronously');
        $this->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not delete unused theme directories after compilation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themeName = $input->getArgument('theme-name');
        $channelOption = $input->getOption('channel');

        $this->io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
        \assert($helper instanceof QuestionHelper);

        if ($input->getOption('channel') && $input->getOption('all')) {
            $this->io->error('You can use either --channel or --all, not both at the same time.');

            return self::INVALID;
        }

        if (!$themeName) {
            $question = new ChoiceQuestion('Please select a theme:', $this->getThemeChoices());
            $themeName = $helper->ask($input, $output, $question);
        }
        \assert(\is_string($themeName));

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB));

        $channels = $this->channelRepository->search($criteria, $this->context)->getEntities();

        if ($input->getOption('all')) {
            $selectedChannel = $channels;
        } else {
            if (!$channelOption) {
                $question = new ChoiceQuestion('Please select a channel:', $this->getChannelChoices($channels));
                $answer = $helper->ask($input, $output, $question);
                $channelOption = $this->parseChannelAnswer($answer);

                if ($channelOption === null) {
                    return self::INVALID;
                }
            }

            if (!$channels->has($channelOption)) {
                $this->io->error('Could not find channel with ID ' . $channelOption);

                return self::INVALID;
            }
            $selectedChannel = [$channels->get($channelOption)];
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('technicalName', $themeName));

        $theme = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        if (!$theme) {
            $this->io->error('Invalid theme name');

            return self::INVALID;
        }

        if ($input->getOption('sync')) {
            $this->context->addState(ThemeService::STATE_NO_QUEUE);
        } elseif (!$input->getOption('no-compile')) {
            // Defer the switch until the background compilation finished so the frontend is
            // not served without CSS meanwhile (no-op when async compilation is disabled).
            $this->context->addState(ThemeService::STATE_DEFER_ASSIGNMENT);
        }

        foreach ($selectedChannel as $channel) {
            $this->io->writeln(
                \sprintf('Set and compiling theme "%s" (%s) as new theme for channel "%s"', $themeName, $theme->getId(), $channel->getName() ?? $channel->getId())
            );

            $this->themeService->assignTheme(
                $theme->getId(),
                $channel->getId(),
                $this->context,
                $input->getOption('no-compile')
            );
        }

        if (!$input->getOption('no-cleanup')) {
            $deletedDirectories = $this->unusedThemeDirectoryDeleter->deleteUnusedDirectories();
            $this->io->note(\sprintf('Deleted %d unused theme %s', $deletedDirectories, $deletedDirectories === 1 ? 'directory' : 'directories'));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    protected function getChannelChoices(ChannelCollection $channels): array
    {
        $choices = [];

        foreach ($channels as $channel) {
            $choices[] = $channel->getName() . ' | ' . $channel->getId();
        }

        return $choices;
    }

    /**
     * @return array<string>
     */
    protected function getThemeChoices(): array
    {
        $choices = [];

        foreach ($this->pluginRegistry->getConfigurations()->getThemes() as $theme) {
            $choices[] = $theme->getTechnicalName();
        }

        return $choices;
    }

    private function parseChannelAnswer(string $answer): ?string
    {
        $parts = explode('|', $answer);
        $channelId = trim(array_pop($parts));

        if (!$channelId) {
            $this->io->error('Invalid answer');

            return null;
        }

        return $channelId;
    }
}
