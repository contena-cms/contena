<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ChannelDomainProvider extends AbstractChannelDomainProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function fetchChannelDomains(): ChannelDomainCollection
    {
        // One domain per sales channel, and always the same one: the language and currency below have
        // to describe the domain whose URL is probed, and a check that silently rotates between
        // domains would report a different page from run to run. `MIN(id)` picks it deterministically.
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`channel_domain`.`id`)) AS `id`,
                   LOWER(HEX(`channel_domain`.`channel_id`)) AS `channel_id`,
                   `channel_domain`.`url` AS `url`,
                   LOWER(HEX(`channel_domain`.`language_id`)) AS `language_id`,
                   LOWER(HEX(`channel_domain`.`currency_id`)) AS `currency_id`
            FROM `channel_domain`
            INNER JOIN `channel` ON `channel_domain`.`channel_id` = `channel`.`id`
            INNER JOIN (
                SELECT `channel_id`, MIN(`id`) AS `id`
                FROM `channel_domain`
                GROUP BY `channel_id`
            ) `first_domain`
                ON `first_domain`.`id` = `channel_domain`.`id`
            WHERE `channel`.`type_id` = :typeId
            AND `channel`.`active` = :active
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_FRONTEND), 'active' => 1]
        );

        $collection = array_map(
            static fn ($domain) => ChannelDomain::create(
                $domain['channel_id'],
                $domain['url'],
                $domain['id'],
                $domain['language_id'],
                $domain['currency_id'],
            ),
            $result
        );

        return new ChannelDomainCollection($collection);
    }
}
