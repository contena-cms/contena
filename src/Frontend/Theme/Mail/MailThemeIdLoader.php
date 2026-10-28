<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Mail;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Storefront\Theme\Mail\MailThemeIdLoaderTest
 */
#[Package('discovery')]
class MailThemeIdLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function load(string $salesChannelId): ?string
    {
        $themeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`theme_id`)) FROM `theme_sales_channel` WHERE `sales_channel_id` = :salesChannelId ORDER BY `theme_id` LIMIT 1',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        if (!\is_string($themeId) || !Uuid::isValid($themeId)) {
            return null;
        }

        return $themeId;
    }
}
