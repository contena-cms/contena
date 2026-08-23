<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Subscriber;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEvents;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class PluginAclPrivilegesSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly KernelPluginCollection $plugins)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AclRoleEvents::ACL_ROLE_LOADED_EVENT => 'onAclRoleLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<AclRoleEntity> $event
     */
    public function onAclRoleLoaded(EntityLoadedEvent $event): void
    {
        $aclRoles = $event->getEntities();

        $additionalRolePrivileges = $this->getAdditionalRolePrivileges();

        foreach ($additionalRolePrivileges as $additionalRole => $additionalPrivileges) {
            foreach ($aclRoles as $aclRole) {
                if ($additionalRole === AclRoleDefinition::ALL_ROLE_KEY || \in_array($additionalRole, $aclRole->getPrivileges(), true)) {
                    $newPrivileges = array_values(array_unique(array_merge($aclRole->getPrivileges(), $additionalPrivileges)));
                    $aclRole->setPrivileges($newPrivileges);
                }
            }
        }
    }

    /**
     * returns a unique, merged array of all role privileges to be added by plugins
     *
     * @return array<string, list<string>>
     */
    private function getAdditionalRolePrivileges(): array
    {
        $rolePrivileges = [];

        foreach ($this->plugins->getActives() as $plugin) {
            $rolePrivileges = array_replace_recursive($rolePrivileges, $plugin->enrichPrivileges());
        }

        return $rolePrivileges;
    }
}
