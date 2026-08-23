<?php declare(strict_types=1);

namespace CtTestPluginAcl;

use Contena\Core\Framework\Plugin;

class CtTestPluginAclProductViewer extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'product.viewer' => [
                'ct_demo_data:read',
            ],
        ];
    }
}
