<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Command;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Maintenance\Channel\Command\ChannelCreateCommand;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @final
 */
#[AsCommand(name: 'channel:create:web', description: 'Creates a new Web channel')]
class ChannelCreateWebCommand extends ChannelCreateCommand
{
    /**
     * @internal
     *
     * @param EntityRepository<SnippetSetCollection> $snippetSetRepository
     */
    public function __construct(
        private readonly EntityRepository $snippetSetRepository,
        ChannelCreator $channelCreator
    ) {
        parent::__construct($channelCreator);
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'App URL for Web channel')
            ->addOption('snippetSetId', null, InputOption::VALUE_REQUIRED, 'Default snippet set')
            ->addOption('isoCode', null, InputOption::VALUE_REQUIRED, 'Snippet set ISO code');
    }

    protected function getTypeId(): string
    {
        return Defaults::CHANNEL_TYPE_WEB;
    }

    protected function getChannelConfiguration(InputInterface $input, OutputInterface $output): array
    {
        $snippetSet = $input->getOption('snippetSetId') ?? $this->guessSnippetSetId($input->getOption('isoCode'));

        return [
            'domains' => [[
                'url' => $input->getOption('url'),
                'languageId' => $input->getOption('languageId'),
                'snippetSetId' => $snippetSet,
            ]],
            'navigationCategoryDepth' => 3,
            'name' => $input->getOption('name') ?? 'Web',
        ];
    }

    private function guessSnippetSetId(?string $isoCode = null): string
    {
        $isoCode = $isoCode ?: 'en-GB';
        $snippetSet = $this->getSnippetSetId($isoCode) ?? $this->getSnippetSetId('en-GB');
        if ($snippetSet === null) {
            throw new \InvalidArgumentException(\sprintf('Snippet set with isoCode %s cannot be found.', $isoCode));
        }

        return $snippetSet;
    }

    private function getSnippetSetId(string $isoCode): ?string
    {
        $criteria = new Criteria()
            ->setLimit(1)
            ->addFilter(new EqualsFilter('iso', str_replace('_', '-', $isoCode)));

        return $this->snippetSetRepository->searchIds($criteria, Context::createCLIContext())->firstId();
    }
}
