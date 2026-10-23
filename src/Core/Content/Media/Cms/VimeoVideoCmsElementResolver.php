<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Cms;

use Contena\Core\Framework\Log\Package;

class VimeoVideoCmsElementResolver extends YoutubeVideoCmsElementResolver
{
    public function getType(): string
    {
        return 'vimeo-video';
    }
}
