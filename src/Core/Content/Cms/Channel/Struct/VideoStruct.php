<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel\Struct;

use Contena\Core\Framework\Log\Package;

class VideoStruct extends ImageStruct
{
    public function getApiAlias(): string
    {
        return 'cms_video';
    }
}
