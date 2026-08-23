<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Frontend\Framework\Routing\Struct\DomainCollection;
use Contena\Frontend\Framework\Routing\Struct\DomainStruct;

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

    public function loadDomains(): DomainCollection
    {
        return DomainCollection::fromArray($this->fetch());
    }

    public function loadDomainForTenant(string $tenantId, string $requestUrl): ?DomainStruct
    {
        $query = $this->connection->createQueryBuilder();

        $query->select(
            'COALESCE(CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\'), :requestUrl) url',
            'LOWER(HEX(COALESCE(domain.id, channel.id))) id',
            'LOWER(HEX(channel.id)) channelId',
            'LOWER(HEX(channel.type_id)) typeId',
            'LOWER(HEX(COALESCE(domain.snippet_set_id, snippet_set.id))) snippetSetId',
            'LOWER(HEX(COALESCE(domain.language_id, channel.language_id))) languageId',
            'LOWER(HEX(theme.id)) themeId',
            'channel.maintenance maintenance',
            'channel.maintenance_ip_allowlist maintenanceIpAllowlist',
            'snippet_set.iso as locale',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        );

        $query->from('channel');
        $query->leftJoin('channel', 'channel_domain', 'domain', 'domain.channel_id = channel.id');
        $query->leftJoin('channel', 'snippet_set', 'default_snippet_set', 'default_snippet_set.iso = :iso');
        $query->innerJoin('channel', 'snippet_set', 'snippet_set', 'snippet_set.id = COALESCE(domain.snippet_set_id, default_snippet_set.id)');
        $query->leftJoin('channel', 'theme_channel', 'theme_channel', 'channel.id = theme_channel.channel_id');
        $query->leftJoin('theme_channel', 'theme', 'theme', 'theme_channel.theme_id = theme.id');
        $query->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id');
        $query->where('channel.tenant_id = UNHEX(:tenantId)');
        $query->andWhere('channel.type_id = UNHEX(:typeId)');
        $query->andWhere('channel.active = 1');
        $query->setParameter('tenantId', $tenantId);
        $query->setParameter('typeId', Defaults::CHANNEL_TYPE_WEB);
        $query->setParameter('iso', Defaults::DEFAULT_LOCALE);
        $query->setParameter('requestUrl', rtrim($requestUrl, '/') . '/');
        $query->orderBy('channel.created_at', 'ASC');
        $query->setMaxResults(1);

        $row = $query->executeQuery()->fetchAssociative();

        if (!\is_array($row)) {
            return null;
        }

        return DomainStruct::fromArray($row);
    }

    /**
     * @return array<string, array<string, string|null>>
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
            'LOWER(HEX(domain.language_id)) languageId',
            'LOWER(HEX(theme.id)) themeId',
            'channel.maintenance maintenance',
            'channel.maintenance_ip_allowlist maintenanceIpAllowlist',
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
        $query->setParameter('typeId', Defaults::CHANNEL_TYPE_WEB);

        /** @var array<string, array<string, string|null>> $domains */
        $domains = FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());

        return $domains;
    }
}
