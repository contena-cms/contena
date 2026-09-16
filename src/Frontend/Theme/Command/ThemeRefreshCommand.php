<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Command;

use Contena\Core\Framework\Context;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'theme:refresh',
    description: 'Refresh the theme configuration',
)]
class ThemeRefreshCommand extends Command
{
    private readonly Context $context;

    /**
     * @internal
     */
    public function __construct(private readonly ThemeLifecycleService $themeLifecycleService)
    {
        parent::__construct();
        $this->context = Context::createCLIContext();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->themeLifecycleService->refreshThemes($this->context);

        return self::SUCCESS;
    }
}
