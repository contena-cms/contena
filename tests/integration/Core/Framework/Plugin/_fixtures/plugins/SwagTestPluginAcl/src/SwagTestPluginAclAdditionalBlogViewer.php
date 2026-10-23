<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Contena\Core\Framework\Plugin;

class SwagTestPluginAclAdditionalBlogViewer extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'blog.viewer' => [
                'swag_demo_data:read',
            ],
        ];
    }
}
