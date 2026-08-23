<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ChannelDomainProvider extends AbstractChannelDomainProvider
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function fetchChannelDomains(): ChannelDomainCollection
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`channel`.`id`)) AS `channel_id`,
                   `channel_domain`.`url` AS `url`
            FROM `channel_domain`
            INNER JOIN `channel` ON `channel_domain`.`channel_id` = `channel`.`id`
            WHERE `channel`.`type_id` = :typeId
            AND `channel`.`active` = :active
            GROUP BY `channel`.`id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['typeId' => Uuid::fromHexToBytes(Defaults::CHANNEL_TYPE_WEB), 'active' => 1]
        );

        $collection = array_map(
            static fn (array $domain) => ChannelDomain::create($domain['channel_id'], $domain['url']),
            $result
        );

        return new ChannelDomainCollection($collection);
    }
}
