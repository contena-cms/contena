<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Mail;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MailThemeIdLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function load(string $channelId): ?string
    {
        $themeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`theme_id`)) FROM `theme_channel` WHERE `channel_id` = :channelId ORDER BY `theme_id` LIMIT 1',
            ['channelId' => Uuid::fromHexToBytes($channelId)]
        );

        return \is_string($themeId) && Uuid::isValid($themeId) ? $themeId : null;
    }
}
