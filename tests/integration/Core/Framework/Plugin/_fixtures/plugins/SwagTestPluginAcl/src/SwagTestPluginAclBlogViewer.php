<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Contena\Core\Framework\Plugin;

class SwagTestPluginAclBlogViewer extends Plugin
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
