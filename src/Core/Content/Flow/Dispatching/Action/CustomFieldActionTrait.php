<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Framework\Uuid\Uuid;

trait CustomFieldActionTrait
{
    /**
     * @param array<string, mixed> $customFields
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>|null
     */
    public function getCustomFieldForUpdating(?array $customFields, array $config): ?array
    {
        $customFields ??= [];
        $customFieldId = (string) ($config['customFieldId'] ?? '');
        $customFieldValue = $config['customFieldValue'] ?? null;
        if ($customFieldId === '' && $customFieldValue === null) {
            return null;
        }

        $customFieldName = $this->getCustomFieldNameFromId($customFieldId);
        if ($customFieldName === null) {
            return null;
        }

        switch ($config['option'] ?? 'upsert') {
            case 'upsert':
                $customFields[$customFieldName] = $customFieldValue;
                break;
            case 'create':
                if (isset($customFields[$customFieldName])) {
                    return null;
                }
                $customFields[$customFieldName] = $customFieldValue;
                break;
            case 'clear':
                if (!isset($customFields[$customFieldName])) {
                    return null;
                }
                unset($customFields[$customFieldName]);
                break;
            case 'add':
                if ($customFieldValue === null) {
                    return null;
                }
                $customFields[$customFieldName] = (array) ($customFields[$customFieldName] ?? []);
                $addData = array_diff((array) $customFieldValue, $customFields[$customFieldName]);
                if ($addData === []) {
                    return null;
                }
                $customFields[$customFieldName] = array_merge($customFields[$customFieldName], $addData);
                break;
            case 'remove':
                if (!isset($customFields[$customFieldName]) || $customFieldValue === null) {
                    return null;
                }
                $customFields[$customFieldName] = (array) $customFields[$customFieldName];
                $removeData = array_intersect($customFields[$customFieldName], (array) $customFieldValue);
                if ($removeData === []) {
                    return null;
                }
                $customFields[$customFieldName] = array_values(array_diff($customFields[$customFieldName], $removeData));
                break;
            default:
                return null;
        }

        return $customFields;
    }

    private function getCustomFieldNameFromId(string $customFieldId): ?string
    {
        $name = $this->connection->fetchOne(
            'SELECT `name` FROM `custom_field` INNER JOIN `custom_field_set_relation` ON `custom_field`.`set_id` = `custom_field_set_relation`.`set_id` WHERE `custom_field_set_relation`.`entity_name` = :entity AND `custom_field`.`id` = :id',
            ['entity' => 'user', 'id' => Uuid::fromHexToBytes($customFieldId)],
        );

        return \is_string($name) && $name !== '' ? $name : null;
    }
}
