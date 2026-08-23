<?php declare(strict_types=1);

namespace CtTestPluginAcl;

use Contena\Core\Framework\Plugin;

class CtTestPluginAclOpenToAllRead extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'all' => [
                'open_to_all:read',
            ],
        ];
    }
}
