<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Channel\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'channel:update:domain',
    description: 'Updates the channel domain with a new domain for all or specific channels matching the previous domain, except API channels',
)]
class ChannelUpdateDomainCommand extends Command
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
        $this->addArgument('domain', InputArgument::REQUIRED, 'Domain of the new channel');
        $this->addOption('previous-domain', null, InputOption::VALUE_OPTIONAL, 'Only apply to this previous domain');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, [
            new PrefixFilter('url', 'default.api'),
        ]));
        $domains = $this->channelDomainRepository->search($criteria, $context)->getEntities();

        $host = $input->getArgument('domain');
        $previousHost = $input->getOption('previous-domain');
        $payload = [];
        foreach ($domains as $domain) {
            if ($previousHost && parse_url($domain->getUrl(), \PHP_URL_HOST) !== $previousHost) {
                continue;
            }

            $payload[] = [
                'id' => $domain->getId(),
                'url' => $this->replaceDomain($domain->getUrl(), $host),
            ];
        }

        $this->channelDomainRepository->update($payload, $context);

        return self::SUCCESS;
    }

    private function replaceDomain(string $url, string $newDomain): string
    {
        $components = parse_url($url);
        if ($components === false) {
            return $url;
        }

        if (\array_key_exists('host', $components)) {
            $components['host'] = $newDomain;
        }

        return $this->buildUrl($components);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts): string
    {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '')
            . ((isset($parts['user']) || isset($parts['host'])) ? '//' : '')
            . (isset($parts['user']) ? (string) $parts['user'] : '')
            . (isset($parts['pass']) ? ':' . $parts['pass'] : '')
            . (isset($parts['user']) ? '@' : '')
            . (isset($parts['host']) ? (string) $parts['host'] : '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (isset($parts['path']) ? (string) $parts['path'] : '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
