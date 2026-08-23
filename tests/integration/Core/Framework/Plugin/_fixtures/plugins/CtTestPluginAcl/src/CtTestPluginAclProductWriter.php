<?php declare(strict_types=1);

namespace CtTestPluginAcl;

use Contena\Core\Framework\Plugin;

class CtTestPluginAclProductWriter extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'product.writer' => [
                'ct_demo_data:write',
            ],
        ];
    }
}
