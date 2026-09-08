<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopId\Fingerprint;

use Contena\Core\Framework\App\ShopId\Fingerprint;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
readonly class ChannelDomainUrls implements Fingerprint
{
    final public const IDENTIFIER = 'channel_domain_urls';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Newly added, removed or changed sales channel domains are an early indication that the shop ID should be changed.
     */
    public function getScore(): int
    {
        return 25;
    }

    public function getStamp(): string
    {
        return $this->generateHash($this->fetchChannelDomainUrls());
    }

    /**
     * @return list<string>
     */
    private function fetchChannelDomainUrls(): array
    {
        return $this->connection
            ->fetchFirstColumn('SELECT url FROM channel_domain');
    }

    /**
     * @param list<string> $urls
     */
    private function generateHash(array $urls): string
    {
        // @phpstan-ignore-next-line contena.hasher
        return \hash('md5', implode('', $urls));
    }
}
