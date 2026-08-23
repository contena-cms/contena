<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Frontend\Framework\Routing\Struct\DomainStruct;

/**
 * Resolves the default web channel of a tenant for requests that are
 * addressed by a tenant convention without a registered channel domain
 * (e.g. ac.contena.cn). The request URL serves as the domain URL.
 *
 * @internal
 */
final class TenantDefaultDomainLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function load(string $tenantId, string $requestUrl): ?DomainStruct
    {
        $query = $this->connection->createQueryBuilder();

        $query->select(
            'COALESCE(CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\'), :requestUrl) url',
            'LOWER(HEX(COALESCE(domain.id, channel.id))) id',
            'LOWER(HEX(channel.id)) channelId',
            'LOWER(HEX(channel.type_id)) typeId',
            'LOWER(HEX(COALESCE(domain.snippet_set_id, default_snippet_set.id))) snippetSetId',
            'LOWER(HEX(COALESCE(domain.language_id, channel.language_id))) languageId',
            'LOWER(HEX(theme.id)) themeId',
            'channel.maintenance maintenance',
            'channel.maintenance_ip_allowlist maintenanceIpAllowlist',
            'default_snippet_set.iso as locale',
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
}
