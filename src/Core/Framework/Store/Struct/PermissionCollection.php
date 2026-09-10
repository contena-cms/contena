<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;

/**
 * @template-extends StoreCollection<PermissionStruct>
 */
class PermissionCollection extends StoreCollection
{
    public function __construct(iterable $elements = [])
    {
        $elements = (array) $elements;
        if ($elements !== [] && array_filter($elements, static fn ($element): bool => $element instanceof PermissionStruct) === []) {
            foreach ($elements as $permission) {
                if (!\is_array($permission) || !isset($permission['entity'], $permission['operation'])) {
                    continue;
                }

                foreach (AclRoleDefinition::PRIVILEGE_DEPENDENCE[$permission['operation']] ?? [] as $operation) {
                    $elements[] = ['entity' => $permission['entity'], 'operation' => $operation];
                }
            }
        }

        parent::__construct(array_values(array_unique($elements, \SORT_REGULAR)));
    }

    /**
     * @return array<string, PermissionCollection>
     */
    public function getCategorizedPermissions(): array
    {
        $categories = [];
        foreach ($this as $permission) {
            $category = match ($permission->getEntity()) {
                'blog', 'category', 'landing_page', 'media', 'cms_page', 'cms_block', 'cms_slot' => 'content',
                'member', 'member_group', 'user' => 'members',
                'channel', 'channel_domain', 'language', 'locale' => 'channels',
                'custom_field_set', 'custom_field' => 'settings',
                'app' => 'app',
                'tag' => 'tag',
                'rule', 'rule_condition' => 'rules',
                'theme' => 'theme',
                default => 'other',
            };
            $categories[$category] ??= new self();
            $categories[$category]->add($permission);
        }

        return $categories;
    }

    protected function getExpectedClass(): string
    {
        return PermissionStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return PermissionStruct::fromArray($element);
    }
}
