<?php declare(strict_types=1);

namespace Contena\Core\Migration\Traits;

use Doctrine\DBAL\Connection;

trait CreateMailTemplateTrait
{



    private function getMailTemplateTypeId(Connection $connection, string $technicalName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function hasTemplateTypeTranslation(Connection $connection, string $mailTemplateTypeByteId, string $languageByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND `language_id` = :languageId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId, 'languageId' => $languageByteId]
        );
    }

    private function getMailTemplateId(Connection $connection, ?string $mailTemplateTypeByteId): ?string
    {
        $result = $connection->fetchOne(
            <<<'SQL'
SELECT `id`
FROM `mail_template`
WHERE `mail_template_type_id` = :mailTemplateTypeId
    AND `system_default` = 1
ORDER BY `created_at` ASC, `id` ASC
LIMIT 1
SQL,
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function hasMailTemplateTranslation(Connection $connection, string $mailTemplateByteId, string $languageByteId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT `mail_template_id` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND `language_id` = :languageId',
            ['mailTemplateId' => $mailTemplateByteId, 'languageId' => $languageByteId]
        );
    }

    /**
     * @return list<string>
     */
    private function getLanguageByteIdsByLocalePrefix(Connection $connection, string $localePrefix, bool $invert = false): array
    {
        $operator = $invert ? 'NOT LIKE' : 'LIKE';

        $languageIds = $connection->fetchFirstColumn(
            \sprintf(
                <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE LOWER(`locale`.`code`) %s :localePrefix
ORDER BY `language`.`created_at` ASC, `language`.`id` ASC
SQL,
                $operator
            ),
            ['localePrefix' => $localePrefix . '-%']
        );
        $languageByteIds = [];
        foreach ($languageIds as $languageId) {
            if (!\is_string($languageId)) {
                continue;
            }

            $languageByteIds[] = $languageId;
        }

        return $languageByteIds;
    }
}
