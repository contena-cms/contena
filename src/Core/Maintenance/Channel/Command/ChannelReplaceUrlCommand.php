<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[AsCommand(
    name: 'channel:replace:url',
    description: 'Replaces the URL of a channel with a new URL',
)]
class ChannelReplaceUrlCommand extends Command
{
    /**
     * @param EntityRepository<ChannelDomainCollection> $channelDomainRepository
     */
    public function __construct(private readonly EntityRepository $channelDomainRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('previous-url', InputArgument::REQUIRED, 'Previous URL of the channel');
        $this->addArgument('new-url', InputArgument::REQUIRED, 'New URL of the channel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();
        $io = new SymfonyStyle($input, $output);
        $previousUrl = trim((string) $input->getArgument('previous-url'));
        $newUrl = trim((string) $input->getArgument('new-url'));

        if (!$this->validateUrls($previousUrl, $newUrl, $io)) {
            return self::FAILURE;
        }

        $domain = $this->findDomainByUrl($previousUrl, $context);
        if (!$domain instanceof ChannelDomainEntity) {
            $io->error('No channels found with URL ' . $previousUrl);

            return self::FAILURE;
        }

        $this->channelDomainRepository->update([[
            'id' => $domain->getId(),
            'url' => $newUrl,
        ]], $context);

        return self::SUCCESS;
    }

    private function validateUrls(string $previousUrl, string $newUrl, SymfonyStyle $io): bool
    {
        if ($previousUrl === '') {
            $io->error('Previous URL: This value can not be empty');

            return false;
        }

        $validator = Validation::createValidator();
        $newUrlViolations = $validator->validate($newUrl, [new Url(requireTld: false), new NotEqualTo($previousUrl)]);
        if (\count($newUrlViolations) === 0) {
            return true;
        }

        foreach ($newUrlViolations as $violation) {
            $io->error('New URL: ' . $violation->getMessage());
        }

        return false;
    }

    private function findDomainByUrl(string $url, Context $context): ?ChannelDomainEntity
    {
        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('url', $url))
            ->setLimit(1);

        return $this->channelDomainRepository->search($criteria, $context)->getEntities()->first();
    }
}
