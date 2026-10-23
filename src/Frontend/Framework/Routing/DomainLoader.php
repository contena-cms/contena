<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Frontend\Framework\Routing\Struct\DomainCollection;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
class DomainLoader extends AbstractDomainLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    public function load(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'loadDomains()')
        );

        return $this->fetch();
    }

    public function loadDomains(): DomainCollection
    {
        return DomainCollection::fromArray($this->fetch());
    }

    /**
     * @return array<string, Domain>
     */
    private function fetch(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select(
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') `key`',
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') url',
            'LOWER(HEX(domain.id)) id',
            'LOWER(HEX(channel.id)) channelId',
            'LOWER(HEX(channel.type_id)) typeId',
            'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
            'LOWER(HEX(domain.currency_id)) currencyId',
            'LOWER(HEX(domain.language_id)) languageId',
            'LOWER(HEX(theme.id)) themeId',
            'channel.maintenance maintenance',
            // @deprecated tag:v6.8.0 - remove the COALESCE fallback to the deprecated `maintenance_ip_whitelist` column
            'COALESCE(channel.maintenance_ip_allowlist, channel.maintenance_ip_whitelist) maintenanceIpAllowlist',
            'snippet_set.iso as locale',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        );

        $query->from('channel');
        $query->innerJoin('channel', 'channel_domain', 'domain', 'domain.channel_id = channel.id');
        $query->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id');
        $query->leftJoin('channel', 'theme_channel', 'theme_channel', 'channel.id = theme_channel.channel_id');
        $query->leftJoin('theme_channel', 'theme', 'theme', 'theme_channel.theme_id = theme.id');
        $query->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id');
        $query->where('channel.type_id = UNHEX(:typeId)');
        $query->andWhere('channel.active');
        $query->setParameter('typeId', Defaults::CHANNEL_TYPE_FRONTEND);

        /** @var array<string, Domain> $domains */
        $domains = FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());

        return $domains;
    }
}
