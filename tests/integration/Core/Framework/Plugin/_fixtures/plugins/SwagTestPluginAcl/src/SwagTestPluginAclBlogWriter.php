<?php declare(strict_types=1);

namespace SwagTestPluginAcl;

use Contena\Core\Framework\Plugin;

class SwagTestPluginAclBlogWriter extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'blog.writer' => [
                'swag_demo_data:write',
            ],
        ];
    }
}
