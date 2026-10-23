<?php declare(strict_types=1);

namespace Contena\Administration\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @internal
 */
class Migration1636121186CopyChannelIdsIntoUserConfig extends MigrationStep
{
    private const CONFIG_KEY = 'sales-channel-favorites';
    private const MAX_RESULTS = 7;

    public function getCreationTimestamp(): int
    {
        return 1636121186;
    }

    public function update(Connection $connection): void
    {
        $ids = $this->fetchUserChannelIds($connection);

        if (!$ids) {
            return;
        }

        $mapping = $this->getMappedData($ids);

        foreach ($mapping as $admin_userId => $channelIds) {
            $slicedIds = \array_slice($channelIds, 0, self::MAX_RESULTS);

            $connection->insert('admin_user_config', [
                'id' => Uuid::randomBytes(),
                'admin_user_id' => $admin_userId,
                '`key`' => self::CONFIG_KEY,
                '`value`' => json_encode($slicedIds, \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param list<array{admin_userId: string, channelId: string, name: string}> $data
     *
     * @return array<string, list<string>>
     */
    private function getMappedData(array $data): array
    {
        $mapping = [];
        foreach ($data as $channelData) {
            $mapping[$channelData['admin_userId']][] = $channelData['channelId'];
        }

        return $mapping;
    }

    /**
     * @return list<array{admin_userId: string, channelId: string, name: string}>
     */
    private function fetchUserChannelIds(Connection $connection): array
    {
        /** @var list<array{admin_userId: string, channelId: string, name: string}> $result */
        $result = $connection->createQueryBuilder()
            ->select('admin_user.id AS admin_userId')
            ->addSelect('LOWER(HEX(translation.channel_id)) AS channelId')
            ->addSelect('translation.name')
            ->from(AdminUserDefinition::ENTITY_NAME, 'admin_user')
            ->innerJoin('admin_user', LanguageDefinition::ENTITY_NAME, 'language', 'admin_user.locale_id = language.locale_id')
            ->innerJoin('language', ChannelTranslationDefinition::ENTITY_NAME, 'translation', 'translation.language_id = language.id')
            ->orderBy('translation.name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $result;
    }
}
